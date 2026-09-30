# TRAM / Transport

Transport je tenantový modul php-core. Poskytuje jedno API pro web a mobil, adaptéry
externích zdrojů a verzovaný import jízdních řádů pro vlastní OpenTripPlanner (OTP).
Jízdenky, platby a frontend nejsou součástí této první etapy.

**Požadovaná architektura je online-first:** TRAM čte dopravní data z online API
a může v PHP spojovat online získané úseky. Na importovaný katalog se obrací
až při výpadku relevantní služby. Současný PID prototyp toto pravidlo ještě
nesplňuje; přesný stav a další kroky uvádí [online-first návrh](../../../docs/tram-online-first.md).

## Co je implementováno

- `TransportApi` používá stávající Router, Response a `X-Internal-Key` middleware.
- `JourneyService` vybírá poskytovatele pokrývající oba konce cesty, volá je souběžně,
  sjednocuje výsledky, řadí je a při výpadku oslovuje nakonfigurované zálohy.
- `ProviderRegistry` obsahuje explicitně povolené adaptéry; konfigurace neurčuje PHP třídy.
- `TransmodelProvider` implementuje Entur a OTP 2.9 Transmodel GraphQL.
- `PidProvider` implementuje Golemio zastávky, odjezdy a polohy vozidel.
- `ResourceService` řeší detaily, živé zdroje, lokální zastávky a propojení OTP/PID ID.
- `HttpModule` poskytuje společný Guzzle transport s omezenou paralelizací, velikostí odpovědi a deadlinem; Transport vlastní HTTP implementaci nemá.
- `TransportRepository` odděluje tenanty i provozní stav poskytovatelů.
- `FeedSyncService` a `GtfsImportService` streamují ZIP/CSV do odděleného snapshotu.
- `GraphService` exportuje přesný snapshot pro OTP a aktivuje až ověřený graf.

Druhy dopravy jsou v existujícím `enumeration`, typ `transport_mode`.
Linky, spoje a jízdní řády používají `transport_*`; tabulky produktů se nemění.

## Instalace

PHP 8.1+ s PDO MySQL, curl, zip a mbstring; MySQL 8. OTP běží zvlášť v Dockeru/JVM.
PHP nemusí obsahovat Java knihovny. Spustit `composer install` podle lockfile (společný HttpModule používá Guzzle).

1. Aplikovat `migrations/schema.sql`, potom `migrations/tram_schema.sql` a
   volitelně `migrations/tram_seed.sql` (12 druhů dopravy). Schémata jsou
   opakovatelná a pouze doplňují chybějící strukturu. Neobsahují data ani veřejné hosty.
2. Pro zvolený TRAM host přidat mapování `host:tram` do stávajících `FRANCHISE_CODES`.
   Zachovat ostatní mapování.
3. Zkopírovat `config/transport.example.json` do soukromé serverové konfigurace,
   např. `/etc/tram/providers.json`; upravit pokrytí, endpointy a identifikaci aplikace.
4. Nastavit serverový `TRANSPORT_PID_TOKEN` pro Golemio. Bez něj bude tento adaptér
   nedostupný a API to přizná; žádný klíč se neposílá klientovi.
5. Spustit:

```bash
php scripts/transport-configure.php --tenant=tram --config=/etc/tram/providers.json
php scripts/transport-sync.php --tenant=tram --feed=pid
```

Konfigurátor upsertuje pouze uvedené poskytovatele a feedy daného tenantu.
Vynechání z konfigurace existujícího poskytovatele neodstraní; vypnout jej pomocí
`published: false`. Existující překlady/upravené číselníky nepřepisuje.
`storage_allowed: true` u feedu musí odpovídat ověřenému oprávnění data ukládat.

`TRANSPORT_STORAGE_DIR` může určit soukromý adresář pro ZIP snapshoty. Výchozí
`temp/transport` je blokovaný existujícím Apache pravidlem pro `/temp/`.
Na jiném webserveru musí být tento adresář také nepřístupný přes HTTP.

## Sestavení a aktivace OTP

Import skončí jako `ready` a vypíše `version_id`. Aktivní data se nezmění.
Použít nový adresář a nový interní port/URL pro každou verzi:

```bash
php scripts/transport-graph.php --tenant=tram --command=export --version=1 --output=/srv/tram/graphs/pid-1
# Do tohoto adresáře dodat příslušný OSM výřez jako streets.osm.pbf.
bash scripts/transport-build-graph.sh /srv/tram/graphs/pid-1
OTP_HEAP=4G bash scripts/transport-serve-graph.sh /srv/tram/graphs/pid-1 8081 tram-pid-1
# Po naběhnutí OTP aktivovat ověřenou verzi:
php scripts/transport-graph.php --tenant=tram --command=activate --version=1 \
  --manifest=/srv/tram/graphs/pid-1/manifest.json \
  --graph-url=http://127.0.0.1:8081/otp/transmodel/v3
```

Číslo verze nahraďte skutečným ID. Paměť závisí na velikosti dat; 4 GB není záruka
pro celou zemi. Spouštěcí skript publikuje pouze na localhost; nevyžaduje Compose plugin. Alternativní Compose konfigurace je v `deploy/transport/compose.yaml` (vyžaduje Docker Compose v2). Při PHP v kontejneru použít
interní síťovou adresu OTP, nikoli localhost PHP kontejneru.

Export vytváří manifest, GTFS a `build-config.json` s explicitním obdobím platnosti snapshotu. Build používá připnutý OTP
2.9.0 a po úspěchu zapisuje kontrolní součty grafu a manifestu. Aktivace kontroluje
tenanta, verzi, součty, platnost a dva prostorově ověřené záznamy zastávek přes
skutečné API plánovače. V jedné DB transakci přepne graf i aktivní snapshot.
**Provozovatel musí na uvedené URL skutečně spustit adresář z manifestu.** Kontrola
zastávek není vzdálený kryptografický důkaz celé sítě. URL se nesmí přepoužít pro
jinou verzi; běžící starý proces ponechat pro rozpracované požadavky a rollback.

Rollback: znovu aktivovat předchozí stále platnou verzi s jejím manifestem a URL.
Předchozí snapshoty a grafy se automaticky nemažou. Provozní úklid musí ponechat
aktivní verzi a alespoň jednu ověřenou zálohu. Chybný import neaktivuje nic.

Výchozí lokální OTP vyhledává podle plánovaných jízdních řádů. Živá data PID čte
PHP adaptér samostatně. Zapojení GTFS-RT do samotného OTP vyžaduje jeho serverový
`router-config.json` s `stop-time-updater`/`real-time-alerts` a Golemio klíčem;
není v příkladu automaticky zapnuté. Klíč nepatří do verzovaného konfiguračního
souboru ani do klienta. Bez tohoto nastavení lokální plánovač nepřepočítává přestupy
podle zpoždění. Entur vrací provozní změny ze své služby.

## API

Prefix: `/api/transport/v1`. **Všechny operace stále vyžadují serverový
`X-Internal-Key`.** Bearer tento klíč nenahrazuje. Web/mobil musí používat serverovou
vstupní vrstvu; interní klíč nesmí být součástí aplikace. Veřejná gateway ani změna
InternalAuthMiddleware nejsou v této implementaci. Bezprostřední zdrojová IP je
omezena na 120 požadavků za minutu/tenant; za serverovou proxy jde o společný limit.

| Metoda/cesta | Význam |
| --- | --- |
| `POST /journeys/search` | Vyhledání spojení |
| `GET /coverage` | Poskytovatelé, schopnosti a konfigurované pokrytí |
| `GET /places?query=Oslo&state=NO&limit=10` | Výběr zastávky; u PID názvový filtr Golemio a prefix lokálního indexu |
| `GET /stops/{id}` | Detail zastávky |
| `GET /stops/{id}/departures?at=...&limit=20` | Odjezdy; `at` je RFC3339, implicitně nyní |
| `GET /journeys/{id}` | Snapshot výsledku, platný 15 minut |
| `GET /journeys/{id}/geometry` | GeoJSON FeatureCollection ve WGS84 |
| `GET /trips/{id}` | Spoj pro konkrétní provozní den |
| `GET /trips/{id}/realtime` | Poloha a zpoždění, pokud je zdroj podporuje |

```json
{
  "from-dest": {"type": "coordinates", "lat": 59.911, "lon": 10.752},
  "to-dest": {"type": "coordinates", "lat": 59.958, "lon": 10.774},
  "from-date": "2026-10-06T10:00:00+02:00",
  "state": "NO",
  "modes": ["tram", "bus", "metro", "train"],
  "max-transfers": 3,
  "limit": 10
}
```

Alternativní destinace: `{"type":"stop","id":"<id z /places nebo výsledku>"}`.
Právě jeden z `from-date` (odjezd nejdříve) a `to-date` (příjezd nejpozději) je
povinný. Neznámé atributy a režimy jsou odmítnuty. `state` znamená ISO kód země;
`city` je kontext klienta, nikoli zákaz překročení hranice. Vlastní výběr plánovače
používá souřadnice obou destinací. Žádné skládání neověřených přestupů mezi API.

Výsledek: stávající obálka `{success,message,data}`; data obsahují `journeys`,
`partial`, `sources`, `warnings`. Každá cesta obsahuje `legs`, `source`, `id`,
`expires_at`. Linky mají jednotná pole `id`, `name`, `code`, `mode`; odjezdy mají objekt `stop` ve stejném formátu jako zastávky. Úsek nese plánované a odhadované časy zvlášť, příznak `realtime`,
odřeknutí, zastávky, linku a dostupnou geometrii. Chybějící údaje jsou `null`.
`live` označuje dotaz na API, ne automaticky aktuální GPS měření.

- Úspěšné API bez cest: HTTP 200 s prázdným seznamem; nespouští fallback.
- Výpadek některého zdroje: částečný výsledek a `partial: true`.
- Nedostupné všechny vhodné zdroje a zálohy: HTTP 503 `sources_unavailable`.
- Nepokrytá cesta: HTTP 422 `unsupported_coverage`.
- Chybějící schopnost: HTTP 422 `unsupported_capability`.
- Expirované ID výsledku: HTTP 404 `expired_journey`, klient hledá znovu.

ID jsou neprůhledná, URL-safe a obsahují namespace tenantu/zdroje/druhu objektu.
`trip_id` zahrnuje provozní datum. U PID odjezdů endpoint provozní den neposkytuje,
proto vrací `external_trip_id` a `trip_id: null`; nevymýšlí se datum podle hodin na
zastávce. PID realtime se naváže pouze při shodě skutečného začátku jízdy s importem.
U intervalových spojů tato verze vazbu na konkrétní vozidlo neodhaduje.
Poloha starší než 90 sekund má `stale: true` a `realtime: false`.

Krátkodobý cache výsledků nyní umožňuje detail/geometrii bez původního textu
hledání. Ukládá však celý výsledek do MySQL, a ten může obsahovat souřadnice
počátku/cíle uživatelské cesty. Proto zatím **nelze tvrdit, že TRAM neukládá
polohu uživatele**. Požadovaná architektura ukládání takových souřadnic i
aktuálních poloh vozidel zakazuje; viz [online-first návrh](../../../docs/tram-online-first.md).

## Další poskytovatelé a provoz

Známý protokol: přidat provider do serverového JSON, nastavit schopnosti daného
adaptéru a skutečné bbox pokrytí. Bbox je konzervativní výběr zdrojů, ne záruka,
že jede spoj mezi každými dvěma body. Jeden poskytovatel může mít více regionů.
Nový protokol: implementovat `JourneySearchProvider` a/nebo `ResourceProvider`,
přidat továrnu do `ProviderRegistry` a allowlist `ConfigurationService`. `JourneyService`
ani veřejný JSON kontrakt se kvůli tomu nemění. Testovat nový adaptér na uložených
odpovědích i proti dostupnému API. Endpointy pocházejí pouze ze serverové konfigurace.

`role: primary` se volá běžně; `role: fallback` s `fallback_for: ["provider-code"]`
se volá pouze při selhání daného relevantního primárního zdroje. U PID je OTP
v aktuálním příkladu stále primární plánovač, což je známý nesoulad s
požadovaným režimem. Golemio adaptér dnes nabízí zastávky, odjezdy a polohy;
pro cestu s přestupy je potřeba další online plánovač nebo PHP skládání z
dostatečných online dat. Poté lze OTP nastavit jako skutečnou zálohu.
Více tenantů může používat stejné tabulky, jejich konfigurace, snapshoty, výsledky
ani stav výpadků se ale nesdílejí. Globální deduplikace feedů mezi tenanty není zapnutá.

HTTP paralelizace je 4, timeout zdroje 4 s, limit těla 4 MB. Hledání má po rozlišení
zastávek rozpočet 5 s pro primární zdroje a 3 s pro zálohy. Rozlišení dvou zastávek
může přidat dva zdrojové dotazy. Tři chyby otevírají circuit na 30 s, HTTP Retry-After
se respektuje (nejvýše hodina); zotavení dovolí jedinou ověřovací žádost.
`min_interval_ms` chrání zdroj napříč PHP procesy pomocí atomického DB zápisu.
Neúspěšné HTTP požadavky se v jednom uživatelském dotazu automaticky neopakují.

Import: limit ZIP 500 MB, rozbalených dat 4 GB, 200 souborů, 10 milionů řádků na CSV.
Soubory se neextrahují do cest dodaných archivem. Nový snapshot má vlastní záznamy,
FK včetně tenantu a verze, validaci kalendářů, návazností a časů. Kalendáře podporují
výjimky, časy nad 24 hodin a GTFS pravidlo noon-minus-12h při změně letního času.
Zápis používá dávky nejvýše 500 řádků / přibližně 1 MB. Originální archiv zachovává i doplňkové GTFS soubory pro OTP. GTFS Flex se záměrně
odmítá, pokud vyžaduje jiný model stop times; NeTEx/JDF importéry zatím nejsou napsané.

Denně synchronizovat feedy podle jejich publikačního intervalu; sestavení nového
grafu provozovat mimo uživatelské požadavky. Zámek v MySQL brání dvěma současným
importům stejného feedu/tenantu. Průběh sledovat v `transport_sync_run`; neúspěchy
obsahují bezpečný kód, nikoli tajné HTTP hlavičky. Pravidelně čistit dočasné výsledky:

```bash
php scripts/transport-cleanup.php --tenant=tram
```

## Ověření

```bash
# Nová dočasná MySQL instance na UNIX socketu; žádný přístup k aplikační databázi.
bash scripts/test-transport.sh
# Volitelné ověření aktuálního velkého GTFS archivu:
TRANSPORT_TEST_PID_ARCHIVE=/absolute/path/PID_GTFS.zip bash scripts/test-transport.sh
# Read-only dotazy na skutečný Entur:
php src/Modules/Transport/tests/live.php
# Pro OTP s testovacími vstupy (viz tests/fixtures.php a osm-fixture.py):
TRANSPORT_TEST_OTP_URL=http://127.0.0.1:18089/otp/transmodel/v3 php src/Modules/Transport/tests/live.php
```

Testy pokrývají opakovatelnou migraci/konfiguraci/import, tenanty, rollback importu,
kalendáře, půlnoc/DST, aktivaci grafu, cache, circuit breaker, fallback, rozdíl mezi
výpadkem a prázdnou odpovědí, HTTP autentizaci a HTTP timeout/size/redirect limity.
Live test OTP používá syntetickou síť, nikoli kompletní produkční síť PID.
Golemio realtime vyžaduje platný token a jeho produkční ověření je samostatný krok.

Primární dokumentace:
- https://developer.entur.org/pages-journeyplanner-journeyplanner/
- https://api.golemio.cz/pid/docs/openapi/
- https://gtfs.org/documentation/schedule/reference/
- https://docs.opentripplanner.org/en/v2.9.0/apis/TransmodelApi/
- https://docs.opentripplanner.org/en/v2.9.0/GTFS-RT-Config/

### Ověřeno při implementaci (27. 9. 2026)

- Izolované MySQL testy: 58 kontrol po doplnění testu časové návaznosti.
- Reálný Entur: vyhledávání, zastávka, odjezdy, datovaný detail a našeptávač.
- Skutečný OTP 2.9.0: sestavení syntetického GTFS/OSM grafu, vyhledávání,
  zastávka, odjezdy a datovaný detail přes stejný PHP adaptér.
- Aktuální PID GTFS: 20 166 zastávek, 900 linek, 87 923 spojů,
  1 793 456 stop times, 3 603 307 bodů geometrie; import v dočasné databázi
  přibližně 175 s. Jde o lokální měření konkrétního feedu, nikoli produkční SLA.
- Živá Golemio API s autentizačním tokenem ani plný PID routing graf nejsou
  produkčně ověřeny. Produkční DB, hosty a běžící služby nebyly změněny.
