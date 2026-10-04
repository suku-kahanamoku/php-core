# TRAM: PHP gateway do Javy

Od 3. 10. 2026 obsahuje tento modul pouze autentizované předání API do Java služeb.
Výpočty, katalogy, adaptéry, GTFS/OSM sběr a transformace, sestavení grafů,
plánování a realtime zajišťují [Java projekty](../../../../../java-tram/README.md).
PHP neimportuje jízdní řády, nepočítá přestupy ani zpoždění a neprovozuje
transportový WebSocket server. Zdrojové implementace původního PHP Transport/Gtfs
byly odstraněny na výslovné zadání uživatele; odstranění není potvrzením úplné
funkční parity. Meze Javy popisuje [PARITY.md](../../../../../java-tram/PARITY.md).

## Zapojení

Frontend → Astro BFF → `/api/transport/v1/...` → Java `/transport/v1/...`.
`api/transport/index.php` používá společný bootstrap, interní klíč, výběr tenantu
z `FRANCHISE_CODES`. Gateway se nepřipojuje k SQL a nezapisuje ani počítadla požadavků.
`TransportModule` sestaví gateway pouze pro explicitně nakonfigurovaný tenant.
`JavaTransportApi` registruje pevný seznam cest; `JavaTransportService` předá
JSON přes injektovaný `HttpModule::client()`. Dopravní obsah nemění.
Veřejná zastávková pole `modes` a `transport_scope` z Java katalogu zachovává
v našeptávači, detailech i zastávkách spojení; barvy a ikony řídí společná
komponenta Astro. MHD/regionální zařazení se konfiguruje u Java feedu/linky
podle [kontraktu metadat](../../../../../java-tram/OTP/API.md#dopravní-metadata-zastávek),
PHP jej neodvozuje ani nepřekládá.

Serverová konfigurace:

```dotenv
TRANSPORT_JAVA_ENABLED=1
TRANSPORT_JAVA_TENANT=tram
TRANSPORT_JAVA_URL=http://127.0.0.1:18095
TRANSPORT_JAVA_TOKEN=<soukromy-serverovy-token-alespon-24-znaku>
```

URL je kořen privátního Java API, bez cesty, query a credentials. Token musí
odpovídat Java API; nikdy nepatří do browseru. Adresa 18095 je lokální country
router, nikoli povinný produkční port. Host TRAM musí být mapovaný v
`FRANCHISE_CODES`; tato mapa vybírá tenant, nenahrazuje autentizaci.
Chybějící konfigurace, vypnutí nebo jiný tenant vrátí 503. Původní PHP režim
ani automatický SQL fallback již neexistují.

## API

| Metoda | Cesta za `/api/transport` | Účel v Javě |
| --- | --- | --- |
| GET | `/v1/coverage` | Skutečně zapojené země a schopnosti |
| GET | `/v1/attributions` | Atribuce vstupů aktivního grafu |
| POST | `/v1/cities/search` | Katalog měst |
| POST | `/v1/places/search` | Hledání a okolní zastávky |
| POST | `/v1/journeys/search` | Vyhledání spojení |
| GET | `/v1/journeys/:id` | Detail uloženého výsledku |
| GET | `/v1/journeys/:id/geometry` | Geometrie cesty |
| GET | `/v1/stops/:id` | Detail zastávky |
| GET | `/v1/stops/:id/departures` | Odjezdy |
| GET | `/v1/trips/:id` | Statický detail a zastávky spoje |
| GET | `/v1/trips/:id/realtime` | Aktuální provozní údaje |
| GET | `/v1/trips/:id/observation` | Okamžité pozorování polohy/zpoždění |
| POST | `/v1/trips/:id/tracking` | Ticket pro Java WebSocket |

Vstupy a odpovědi určuje [Java API](../../../../../java-tram/OTP/API.md).
List/search přijímá `q`, `sort`, `projection`, `page`, `limit` v JSON těle;
PHP je nepřekládá na SQL. GET query má omezené názvy `at`, `limit`,
`stop_coordinates`; atribuce query nepřijímají. Administrace, synchronizace,
build a libovolné proxy URL nejsou veřejné cesty gateway.

Gateway zachová status, JSON obálku a `Retry-After` Javy, nastaví `no-store`.
Síťové selhání vrací 503, neplatná upstream odpověď 502 bez surového těla.
Limit hledání je 24 s, ostatních operací 9 s, připojení 1,5 s a odpovědi 16 MB.
Reálné pokrytí neznamená všechny dopravce země ani dostupnou GPS každého spoje.
Gateway transparentně předává také Java `estimated_progress` pro spoje bez
registrované služby polohy. Odhad podle jízdního řádu počítá realtime backend;
PHP nevytváří polohu, zpoždění ani fallback při výpadku existující služby.

## Provoz a kontroly

Lokální Java sync/build a explicitní Cloudflare deploy ovládá nová serverová
služba `TransportModule::localPipeline($tenant, $env, HttpModule::client())`.
Po administrační autorizaci volej `submit('sync_build')`, `submit('deploy')`
nebo `status()`. PHP jen zařazuje pevné úlohy; lokální Java runner je vyzvedává
přes odchozí HTTPS a spouští `sync_build.sh`/`deploy.sh`. Není potřeba veřejný
port na PC ani čekající PHP request. Zapnutí vyžaduje
`TRANSPORT_LOCAL_PIPELINE_ENABLED=1` a soukromý serverový
`TRANSPORT_LOCAL_PIPELINE_TOKEN` (Cloudflare Admin token), vedle stávajícího
TRAM tenanta a HTTPS `TRANSPORT_JAVA_URL`. Klíč nepatří do frontendu.
Tato služba není registrovaná ve veřejné gateway; budoucí admin UI musí nejprve
ověřit oprávnění. Viz [lokální pipeline](../../../../../java-tram/LOCAL_PIPELINE.md).

Synchronizaci a grafy provozujte podle [Java služby OTP](../../../../../java-tram/OTP/README.md).
PHP transportové cron/configure/build/serve skripty a provider presets byly
odstraněny. Při nasazení odstraňte jejich staré cron/supervisor položky a obnovte
PHP OPcache; Java služby a jejich soukromé API musí být dostupné před přepnutím.

Gateway nevyžaduje žádné databázové schéma ani SQL credentials.
Autentizace interním klíčem a výběr tenantu probíhají ze serverové konfigurace.
SQL rate limiter byl pro tuto gateway odstraněn; Java `Retry-After` a HTTP
backpressure se nadále předávají. Limity provozu patří Java API nebo vstupní proxy.
Staré TRAM SQL schéma, seed a modulární migrace byly odstraněny z projektu.
Gateway je nepotřebuje. Existující dopravní tabulky ani data tento refaktor nemaže.

```sh
bash scripts/test-java-gateway.sh
bash scripts/test-http.sh
composer lint
git diff --check
```

Gateway test používá skutečný PHP HTTP entrypoint a fixture Java server,
bez MySQL a bez vytváření schémat. Testovací autoloader zakazuje přístup k
Database i SQL RateLimiteru; každá zaregistrovaná cesta musí fungovat bez nich.
Ověřuje autentizaci, tenant, route allowlist, JSON, chyby a Java backpressure.
Fixture neprokazuje živý Java graf; lokální konfigurace a vybrané skutečné cesty
jsou samostatně v [INTERNATIONAL-LOCAL.md](../../../../../java-tram/OTP/INTERNATIONAL-LOCAL.md).
