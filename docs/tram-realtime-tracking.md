# TRAM: živá poloha a zpoždění

Implementace používá existující adaptéry Transportu a HttpModule. Souřadnice vozidel ani uživatelů se nezapisují do DB, souborů, URL nebo historie prohlížeče. Zastávky a plánované jízdní řády zůstávají statickým katalogem pro výpadky.

## API a tok dat

1. Astro BFF přijme `POST /api/transport/tracking/` s `{ "id": "<dated-trip-id>" }`, ověří origin a zavolá php-core vlastními serverovými credentials.
2. `POST /transport/v1/trips/:id/tracking` ověří tenant, datum a schopnost adaptéru `realtime`. Vrací `status: available`, URL WebSocketu, podepsaný ticket a `expires_at`, nebo `unsupported`/`disabled`. Ticket je platný 15 minut, pouze pro daný tenant a konkrétní spoj s datem.
3. Prohlížeč otevře WebSocket a pošle `{ "type": "subscribe", "ticket": "..." }`. Ticket se neposílá v URL ani v logu. Každých 20 sekund posílá `{ "type": "ping" }`.
4. `TrackingHubService` sdílí jeden odběr podle `tenant + trip-id`. Gateway neblokujícím HttpModule klientem volá `GET /transport/v1/trips/:id/observation`. Tento endpoint používá běžný ResourceService, tenant, kvóty, circuit breaker a existující adaptéry. API klíč i tenant host pocházejí výhradně ze serverové konfigurace.
5. Odběratelé dostávají `{ "type": "observation", "trip": "...", "data": { "status": "live", "position": { "lat": 50.1, "lon": 14.4 }, "observed_at": "...", "valid_until": "...", "delay_seconds": 480, "cancelled": false } }`.
6. Gateway neuchovává poslední polohu pro nové odběratele. React uchovává aktuální pozorování pouze po dobu otevřeného detailu. Po nejvýše 30 sekundách od času pozorování se poloha i živé časové predikce odstraní, i když síť úplně zamrzne. Frontendový badge vedle označení spoje si samostatně ponechá poslední potvrzené zpoždění, aby při obnovování nemizel; tooltip a přístupný popisek jej označí jako poslední známý údaj. Tato zobrazovací hodnota neprodlužuje platnost GPS ani se nepoužívá pro výpočty. Potvrzená nula zobrazí zelené „Bez zpoždění“, chybějící údaj neutrální „Zpoždění neznámé“. Zavření detailu nebo skrytí stránky odpojí odběry. Obnovení stránky provede nové ověření.

Odpovědi mají `Cache-Control: no-store`. Neznámé zpoždění není nula a nezobrazuje se jako „jede včas“. Zastaralá poloha nemá katalogový fallback. Endpoint `/realtime` zůstává zpětně kompatibilní; normalizovaný `/observation` je určen gateway.

## Podpora poskytovatelů

- **Golemio/PID:** existující adaptér poskytuje GPS a aktuální zpoždění. Před vydáním polohy ověřuje datum a začátek konkrétní jízdy proti online detailu, příznak tracking, rozsah souřadnic a čas měření. Živá komunikace a toto ověření byly zkontrolovány proti skutečnému API; tajný klíč ani polohy se při kontrole neukládaly.
- **Spojenka + IDS JMK:** Spojenka dodává vyhledávání a detail. Samostatný `IdsJmkProvider` doplňuje GPS z veřejného GTFS Realtime KORDIS a zpoždění konkrétního vozidla z online API IDS JMK. Preset ověřuje brněnské CIS linky `737001–737099` (tramvaje/trolejbusy) a `738001–738099` (autobusy); nepokrývá automaticky všechny regionální či vlakové spoje. Vazba používá oficiální `api.txt` (linka/číslo spoje → GTFS trip), kalendář s výjimkami a přesné časy požadovaných zastávkových výskytů. Duplicitní či rozporná identita nevydá polohu. Další zdroje bez doložené vazby vracejí `unsupported`.
- IDS JMK publikuje zdroje na [stránce veřejných dat](https://www.idsjmk.cz/a/kontakty.html): `https://kordis-jmk.cz/gtfs/gtfs.zip` a `https://kordis-jmk.cz/gtfs/gtfsReal.dat` (CC BY 4.0). Zpoždění se čte z `https://www.idsjmk.cz/api/traffic-state/line/{line}`; vybírá se konkrétní `routeId` a `carNum`, nikdy průměr celé linky. Toto JSON API nemá vlastní čas měření: používá se pouze s čerstvou HTTP odpovědí, čerstvým GTFS měřením stejného vozidla a geograficky konzistentním záznamem (do 500 m kvůli rozdílným okamžikům aktualizace). Platnost zpoždění končí nejpozději s GTFS měřením. Při chybě párování zůstává zpoždění neznámé, GPS může být stále dostupná.
- KORDIS nyní vynechává `start_date` a `start_time` vozidel. Proto adaptér přijímá jen dnešní ověřenou jízdu v jejím časovém okně; historický spoj nemá živou polohu. Původní čas měření musí být mladší než 30 sekund. I úspěšný HTTP request může vrátit starší měření, které se nezobrazí jako živé.
- Spojenka detail doplňuje chybějící statické souřadnice přes detail konkrétních zastávek. `GET /transport/v1/trips/:id?stop_coordinates=0` tato doplňující volání vynechá, takže statický seznam nečeká na souřadnice. Astro dočte souřadnice odděleně jen pro otevřenou osu, která je potřebuje. Výchozí hodnota 1 zachovává původní kontrakt. Shodné ID se načítá jednou, odpověď musí mít stejné ID. Selhání doplnění neznepřístupní jízdní řád. Výchozí sdílená kvóta Spojenky je 8 požadavků za sekundu; i každé doplnění spotřebuje kvótu.
- Nový GPS zdroj implementuje `ResourceProvider`, případně vícekrokový `OnlineResourceProvider`, pro `realtime`. `RealtimeReferenceProvider` dovoluje doložené mapování identity jiného poskytovatele bez závislosti Core na konkrétní integraci. Gateway ani frontend nemusí znát jeho doménu či autentizaci. Adaptér musí dodat ověřené měření s timestampem. Samotný geografický překryv nestačí.
- Aktuálně implementované Golemio i IDS JMK transporty používají HTTP. Gateway ho volá ve sdíleném intervalu; spojení gateway → prohlížeč je WebSocket. Nativní upstream WebSocket/SSE zatím není implementován. Jeho přidání patří do HttpModule a konkrétní integrace, ne do browsera.

## Zapnutí IDS JMK

`config/transport.idsjmk.example.json` obsahuje český preset s adaptérem `idsjmk`. Při aktualizaci již nakonfigurovaného tenantu přidejte jeho definici do úplné současné konfigurace; konfigurační CLI aktualizuje uvedené definice, proto nepřepisujte vlastní nastavení poskytovatelů příkladem. IDS JMK zde nevyžaduje API klíč. Nastavení `source_provider` musí odpovídat skutečnému kódu Spojenky. Mapování CIS rozsahů patří do konfigurace, nelze ho odhadovat z podobného názvu linky.

Statický ZIP a index identity mají souborovou mezipaměť pod `TRANSPORT_STATIC_CACHE_DIR` (jinak oddělený dočasný adresář podle systémového uživatele). Před každým použitím se zdroj online ověří podmíněným HTTP požadavkem. Při jeho výpadku se z mezipaměti nevydává domnělá živá poloha. Indexy obsahují pouze veřejný jízdní řád, žádnou telemetrii. První zpracování nové verze linky může trvat několik sekund, další spoje téže linky a dne sdílí index. Adresář musí být zapisovatelný uživatelem PHP; staré verze lze mimo aktivní provoz smazat a znovu se načtou. Všechny požadavky stále procházejí společným HttpModule, deadline a kvótami.

## Časy a přestupy

`JourneyService` obohatí celou dostupnou sadu kandidátů před finálním řazením a limitem. `PidJourneyEnrichmentService` doplní odjezdy i příjezdy ze zastávkových predikcí. `JourneyRealtimeService` navíc v rámci sdíleného rozpočtu ověří nejvýše čtyři různé jízdy; pozorování se v rámci požadavku sdílí. Následuje `JourneyTimingService`:

- Prioritu má predikce konkrétní zastávky. Při chybějícím příjezdu se zpoždění odjezdu může přenést jako výslovně označený odhad. Aktuální vozidlové zpoždění nepřepisuje historické zastávkové časy.
- Pěší přesun začne nejdříve po aktuálním příjezdu a zachová svou délku. Čekání může část zpoždění pohltit.
- Čas navazujícího vozidla se neposouvá podle předchozího. Jeho vlastní odjezd musí vycházet po příjezdu + minimálním čase přestupu. PID vlastní plánovač zachovává minimum 180 sekund; bez údaje zdroje je konzervativní minimum mezi dvěma vozidly 60 sekund. Samostatně vyjádřená chůze zachovává celý svůj čas.
- Zrušené nebo nestihnutelné cesty se ze serverových výsledků vyřadí. Znovu se ověří odjezd/příjezd požadovaný uživatelem, přepočte délka a seřadí výsledky. Výsledek je omezen kandidáty a časovým rozpočtem dostupných online poskytovatelů; nejde o kompletní celosvětový nový routing po každé změně GPS.
- React používá společný `trackedJourney` pro časy, celkovou dobu a detail. Nově nestihnutelný přestup označí a místo zdánlivě platné délky cesty zobrazí varování s pokynem vyhledat znovu. Sám nevymýšlí náhradní spoj ani nezmění URL výběru.
- Celková doba = aktualizovaný příjezd − aktualizovaný odjezd. U přímé jízdy posunuté na obou koncích o 8 minut zůstane samotná délka jízdy stejná. Zpoždění posledního vozidla může celou cestu prodloužit; zpoždění prvního může spotřebovat čekání. Zpoždění se nikdy nesčítají slepě.
- Dialog a mezilehlé zastávky ukazují dostupné predikce, jinak budoucí odhad z živého zpoždění s `≈`. Původní plánované časy zůstávají pro identitu zastávkového výskytu a URL. SQL journey cache nadále ukládá pouze plánované časy a plánovanou délku.

## Konfigurace a provoz

Stejné prostředí načítá php-core API a samostatný CLI proces. Skutečné hodnoty patří do neveřejného `.env`, nikoliv do frontendového bundlu:

```dotenv
TRANSPORT_TRACKING_SECRET=<random-secret-at-least-32-bytes>
TRANSPORT_TRACKING_WS_URL=wss://tram.example/transport-live
TRANSPORT_TRACKING_LISTEN=websocket://127.0.0.1:8091
TRANSPORT_TRACKING_CORE_URL=https://php-core.example/api
TRANSPORT_TRACKING_ORIGINS=https://tram.example
TRANSPORT_TRACKING_TENANTS={"tram":"tram.example"}
TRANSPORT_TRACKING_MAX_TRIPS=10
TRANSPORT_TRACKING_INTERVAL=10
# INTERNAL_API_KEY je existující serverový klíč php-core.
```

`CORE_URL` musí obsahovat stejný prefix jako Astro `PHP_CORE_URL`; `/transport/v1/...` se přidává automaticky. Host v mapě musí přes `FRANCHISE_CODES` odpovídat klíči tenantu. Lokální URL může být `ws://127.0.0.1:8091`, origin například `http://127.0.0.1:4331`.

```bash
composer install --no-dev
php bin/transport-tracking.php start
```

Vyžaduje PHP CLI s pcntl/posix na Linuxu. Pro produkci proces spravuje systemd/Supervisor; ukázka systemd:

```ini
[Service]
Type=simple
User=www-data
WorkingDirectory=/srv/php-core
ExecStart=/usr/bin/php /srv/php-core/bin/transport-tracking.php start
Restart=on-failure
RestartSec=3
RuntimeDirectory=tram-tracking
Environment=TRANSPORT_TRACKING_RUNTIME_DIR=/run/tram-tracking
```

Před gateway patří HTTPS reverse proxy s WebSocket upgrade, limitem spojení/požadavků na IP a timeoutem přesahujícím heartbeat; loopback port se nepublikuje přímo. V nginx pro vybranou cestu například:

```nginx
location = /transport-live {
    proxy_pass http://127.0.0.1:8091;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 60s;
    proxy_buffering off;
}
```

Jedna instance má jeden event-loop worker, maximálně 500 přijatých spojení a ve výchozím nastavení 10 různých sledovaných jízd. Odběratelé stejné jízdy limit jízd nezvyšují. Globální odchozí limit je 60 požadavků/minutu včetně opakovaného otevírání; další omezení uplatňuje php-core a poskytovatel. Interval lze nastavit na 10–30 sekund, počet jízd na 1–100, ale zvýšení počtu samo nezvyšuje kvóty. Při zahlcení raději není čerstvá poloha než zastaralý „live“ bod.

Pro další škálování je nutné rozdělit vlastníky odběrů podle tenant+trip (nebo zavést společný broker bez perzistence telemetrie) a sjednotit kvóty. Pouhé spuštění více stejných workerů by způsobilo duplicitní upstream volání. Nasazení serveru, reverse proxy a tajných hodnot tento refaktor automaticky neprovádí; bez nich session vrací `disabled`.

## Ověření

- `bash scripts/test-transport.sh`: izolovaná DB, podpis ticketu, tenant, expiry, deduplikace, zpoždění, přestup, chůze, limit/řazení, sanitizace cache.
- `bash scripts/test-http.sh`: skutečné loopback HTTP v event loopu, heartbeat, velikost odpovědi, zamítnutí redirectu; ostatní existující integrační kontroly.
- Astro: `npm test`, `npm run build`, `npm run format:check`, `npm run test:browser`. Browser test používá řízené WebSocket zprávy, kontroluje badge, mapu, expiry a uvolnění odběru.
- Samostatně byl proveden reálný WebSocket handshake a dvě souběžná připojení proti CLI gateway s HTTP fixture; jeden upstream request obsloužil oba a heartbeat fungoval během čekání. To je důkaz lokálního provozu, nikoli produkčního nasazení.

### Lokální ověření IDS JMK (1. 10. 2026)

Skutečné online zdroje byly ověřeny proti dnešnímu brněnskému spoji 68/1053. Přes Astro BFF byl vydán ticket a skutečný prohlížeč obdržel z běžící gateway zprávu `live` s polohou a zpožděním 0 sekund. Přechod přes `stale` při starším upstream měření správně nezobrazuje historickou GPS. Tato kontrola dokládá místní propojení, nikoli produkční nasazení. Testovací fixtures navíc ověřují kladné zpoždění, chybnou jízdu, kalendářní výjimky, duplicitní vozidla, stará měření a poškozený protobuf.

### Oprava pokrytí brněnských tramvají a trolejbusů

CIS identity ze Spojenky používají i rozsah `737…` (identity ověřené v živém vyhledávání na linkách 3, 12, 35 a 39); původní preset obsahoval pouze `738…`. Proto u tramvají/trolejbusů vůbec nevznikl odběr (`unsupported`). Preset nyní obsahuje oba rozsahy a regresní kontrola ověřuje také 737003, 737012, 737035 a 737039. Stávající tenant musí mít nové mapování i v uložené konfiguraci; samotný git pull JSON příkladu nestačí. Přesná vazba na GTFS jízdu, kalendář i platnost GPS se stále ověřují stejně.

Po opravě byl ve skutečném prohlížeči ověřen spoj 3/1118: WebSocket doručil čerstvou polohu a zpoždění 120 sekund, dialog zobrazil badge „Zpoždění 2 min“ a bod mezi Burianovým náměstím a Táborem. Při této kontrole před následnou UX úpravou oba živé údaje zmizely po zestárnutí upstream měření nad 30 sekund. Nyní badge ponechává poslední známé zpoždění podle popisu výše; platnost GPS zůstává stejná.
