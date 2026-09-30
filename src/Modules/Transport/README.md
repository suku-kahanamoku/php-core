# TRAM / Transport

Transport je tenantový modul php-core. Poskytuje jedno API pro web a mobil, adaptéry
externích zdrojů a verzovaný import jízdních řádů pro vlastní OpenTripPlanner (OTP).
Jízdenky, platby a frontend nejsou součástí této první etapy.

**Online-first:** TRAM čte dopravní data z online API. Spojenka doplňuje české
našeptávání a plánování; Entur obsluhuje své pokrytí, Golemio poskytuje PID data
včetně omezeného skládání cest v PHP. Společný `ProviderSelectionService`
vybírá zdroje podle tenantu, schopnosti, státu, města a pokrytí GPS.
Importovaný katalog/OTP se používá až při výpadku odpovídajícího zdroje.
Podmínky použití vývojového endpointu Spojenky, chybějící národní fallback,
přesnost geografického výběru a zbývající nesoulad cache popisuje
[online-first návrh](../../../docs/tram-online-first.md).

## Co je implementováno

- `TransportApi` používá stávající Router, Response a `X-Internal-Key` middleware.
- `JourneyService` vybírá poskytovatele pokrývající oba konce cesty, volá je souběžně,
  sjednocuje výsledky, řadí je a při výpadku oslovuje nakonfigurované zálohy.
- `ProviderRegistry` obsahuje explicitně povolené adaptéry; konfigurace neurčuje PHP třídy.
- `SpojenkaProvider` a `SpojenkaMapper` implementují české online zastávky, cesty a detaily spojů.
- `TransmodelProvider` implementuje Entur a OTP 2.9 Transmodel GraphQL.
- `PidProvider` implementuje online Golemio zastávky, odjezdy, detaily spojů a polohy vozidel.
- `PidOnlineJourneyService` skládá časově ověřené přímé jízdy a jeden přestup z online stop times a detailů jízd.
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
Při přechodu ze starší konfigurace znovu spustit `transport-configure.php`, aby
se uložená role `pid-otp` změnila z `primary` na `fallback`. Samotná úprava
vzorového JSON běžící tenant nepřepne.

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
| `GET /places?query=Oslo&state=NO&limit=10` | Výběr zastávky; u PID názvový filtr Golemio; lokální index pouze po výpadku |
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
`type: coordinates` označuje bod vybraný pro plánování cesty, nikoli tvrzení
že jde o aktuální polohu člověka. Pro GPS polohu uživatele má klient poslat
`{"type":"current_location","lat":50.075,"lon":14.42,"observed-at":"2026-09-30T10:00:00Z"}`
s časem pořízení skutečného měření. Backend přijme jen fix starý nejvýše
30 sekund a při dalším hledání potřebuje nové měření. Tuto polohu neukládá
ani ji nevydává z historie. Příklad času je ilustrační; v požadavku musí být aktuální.
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
- Výpadek některého zdroje: částečný výsledek a `partial: true`; lokální
  záloha nese verzi snapshotu a UTC čas dokončeného importu.
- Omezené PID online skládání: `partial: true` a `source.limited: true`
  i při úspěšném API; vyhledání nemusí být úplné.
- Nedostupné všechny vhodné zdroje a zálohy: HTTP 503 `sources_unavailable`.
- Nepokrytá cesta: HTTP 422 `unsupported_coverage`.
- Chybějící schopnost: HTTP 422 `unsupported_capability`.
- Expirované ID výsledku: HTTP 404 `expired_journey`, klient hledá znovu.

ID jsou neprůhledná, URL-safe a obsahují namespace tenantu/zdroje/druhu objektu.
`trip_id` zahrnuje provozní datum. U PID odjezdů endpoint provozní den neposkytuje,
proto vrací `external_trip_id` a `trip_id: null`; nevymýšlí se datum podle hodin na
zastávce. PID realtime se naváže pouze při shodě začátku jízdy s online detailem provozního dne.
U intervalových spojů tato verze vazbu na konkrétní vozidlo neodhaduje.
Souřadnice vozidla se vracejí pouze při ověřené instanci spoje, aktivním
sledování, platném bodu a měření starém nejvýše 30 sekund. V ostatních
případech jsou `position`, `bearing`, `speed_kmh` a `observed_at` `null`
a `realtime: false`; stará poloha se nesmí ukazovat jako poslední známá.
Endpoint poskytuje jednu aktuální observaci na vyžádání; pro průběžnou mapu musí
klient posílat další požadavky a znovu vyhodnocovat `realtime` a `observed_at`.
Běžná statická poloha zastávky a plánovaná geometrie trasy nejsou polohou vozidla.

Krátkodobá cache detailu má whitelist polí: z odpovědi pro souřadnicový dotaz
odstraňuje názvy a souřadnice bodů, pěší geometrii, predikce i telemetrii.
U dotazu mezi veřejnými zastávkami zachovává jejich statické polohy a
plánovanou geometrii linky. Cache nicméně stále ukládá omezený plánovaný
výsledek do MySQL a detail jej čte; to je zbývající odchylka od cílového
pravidla „DB jen záložní katalog“ popsaná v [návrhu](../../../docs/tram-online-first.md).

## Další poskytovatelé a provoz

Známý protokol: přidat provider do serverového JSON, nastavit schopnosti daného
adaptéru a skutečné bbox pokrytí. Bbox je konzervativní výběr zdrojů, ne záruka,
že jede spoj mezi každými dvěma body. Jeden poskytovatel může mít více regionů.
Nový protokol: implementovat `JourneySearchProvider` a/nebo `ResourceProvider`,
přidat továrnu do `ProviderRegistry` a allowlist `ConfigurationService`. `JourneyService`
ani veřejný JSON kontrakt se kvůli tomu nemění. Testovat nový adaptér na uložených
odpovědích i proti dostupnému API. Endpointy pocházejí pouze ze serverové konfigurace.

`role: primary` se volá běžně; `role: fallback` s `fallback_for: ["provider-code"]`
se volá pouze při selhání daného relevantního primárního zdroje. V příkladu
je `pid` primární online zdroj a `pid-otp` záloha při jeho výpadku.
PID online skládání používá čtyřhodinové okno a nejvýše jeden přestup na
identické zastávce. Vrací `partial: true` a varování, protože omezený počet
kandidátů nemůže dokázat úplnost výsledků. Pro souřadnice a složitější trasy
je nutný další online plánovač; úspěšná prázdná odpověď nespouští OTP.
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
Golemio realtime i online skládání spojů vyžadují platný token a jejich
produkční ověření je samostatný krok. Výchozí limit Golemio je 20 požadavků
za 8 sekund na klíč; současná omezení počtu kandidátů omezují jeden dotaz,
ale vyšší souběh vyžaduje sdílené řízení kvóty nebo smluvně vyšší limit.

Primární dokumentace:
- https://developer.entur.org/pages-journeyplanner-journeyplanner/
- https://api.golemio.cz/docs/static/vp-output-gateway/openapi.json
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

### Ověřeno po přepnutí na online-first (30. 9. 2026)

- Izolované MySQL testy: 92 kontrol včetně přímé jízdy, jednoho přestupu,
  tříminutové návaznosti, příchodu do času, půlnoci, prázdné online odpovědi,
  výpadku API a UTC stáří záložního snapshotu.
- Živé PID API nebylo voláno; token ani produkční tenant nejsou v testech.

### České online zdroje a oblast hledání

`config/transport.cz-online.example.json` je lokálně ověřený vzor pro Spojenku
plus Golemio; nepřidává celostátní import ani oprávnění ke skladování dat.
Před použitím v produkci vyřešit podmínky a limity vývojového serveru Spojenky.
Konfiguraci sloučit s existujícími zdroji a jejich fallback vazbami; upsert
neodstraňuje starší poskytovatele.

Pro municipalitu lze přidat `cities` do položky pokrytí, například
`{"country":"CZ","cities":["Brno"],"bbox":[16.4,49.0,16.9,49.4]}`.
Jde o ilustrační obdélník, nikoli přesné hranice Brna. Národní zdroj se
neomezuje na seznam měst. Přidání nové služby vyžaduje adaptér jen tehdy,
pokud používá nový protokol; další instanci téhož API stačí nakonfigurovat.

Našeptávání: `POST /v1/places/search` s `q.name.$regex`, `q.state`, `q.city`,
volitelnou čerstvou GPS v těle a standardními `limit/page/sort/projection`.
Řádky jsou pod `data`, maximálně 50 (web zobrazuje 20), stránka 1.
Detaily se směrují přes tenantové ID původního poskytovatele.

`NearestStopService` převádí `current_location` na nejbližší online zastávku
v okruhu 2 km před hledáním cesty. Používá schopnost `nearby_stops`, sdílený
HttpModule a tenantový fallback pouze při výpadku. Odpověď vrací veřejný název
zastávky v `resolved_places`; GPS ani toto rozlišení se nepersistuje.
Čas cesty začíná/končí na zastávce, bez pěší cesty od/k GPS bodu.

Výsledky míst mohou obsahovat `city` (obec z ověřených metadat poskytovatele;
u Spojenky `placeHierarchy.MUNICIPALITY`). Vyhledání spojení přidává
`area: {city: string|null}` pro automatický výběr oblasti ve frontendu.
`JourneyAreaService` ověřuje společnou obec koncových zastávek a konců
zobrazených úseků; neověřuje celou geometrii ani průjezdní zastávky.
Meziměstská cesta, prázdný výsledek či chybějící metadata vrací `null`.
Jde o odvozená metadata odpovědi, nikoli nový zdroj plánování nebo GPS cache.

`POST /v1/places/search` podporuje také nabídku nejbližších zastávek: vynechané
`q.name` vyžaduje čerstvé `q.latitude`, `q.longitude`, `q.observed_at` v POST těle.
`NearestStopService::search()` vrací seznam do 2 km řazený podle geografické
vzdálenosti, bez duplicit ID, nejvýše podle `limit`. Stejnou metodu používá
`resolve()` při plánování z GPS. Úspěšný prázdný seznam nespouští fallback;
statický katalog se použije jen pro selhané poskytovatele. Výběr uživatelem
je volitelný a seznam ani měření se neukládá do cache cest.

Detail jízdy ze Spojenky přidává volitelné `metadata`: `line`, `number`,
`name`, `service_date` a `notes` s `scope` (trip/line), `texts` dle jazyka
 a `default_language`. Veřejné číslo pochází z explicitních registry numbers
CISJR/KADR/PID; persistent ID a technická čísla PTI se za číslo spoje nevydávají.
Poznámky se přebírají z `connection.timetableNotes` a `line.timetableNotes`.
Metadata neobsahují telemetrii a neodvozují provozní kalendář ani garantované
návaznosti. Aktuálně ověřená odpověď pro 35/1093 poskytuje číslo, trasu a poznámky
linky; kontakt dopravce, „jede v X“ a garanci návaznosti na 38 v ní nejsou.
Nejde o změnu synchronizace ani databázového schématu.

Spojenka mapuje atributy každého zastavení do `tariff_zones` (seznam
`system`/`zone`), `request_stop` (bool nebo null při chybějících datech)
a `route_km` (nezáporná konečná hodnota nebo null). Zdroje jsou
`tariffZones`, příznak `REQUEST_STOP` a `kmPosition`. Nula zůstává známou
hodnotou; chybějící nebo neplatná kilometráž se nepřepočítává z GPS ani
nedoplňuje z jiných zdrojů. Údaje jsou součástí online detailu jízdy,
bez nové migrace.
