# TRAM: živá poloha a zpoždění

Implementace používá existující adaptéry Transportu a HttpModule. Souřadnice vozidel ani uživatelů se nezapisují do DB, souborů, URL nebo historie prohlížeče. Zastávky a plánované jízdní řády zůstávají statickým katalogem pro výpadky.

## API a tok dat

1. Astro BFF přijme `POST /api/transport/tracking/` s `{ "id": "<dated-trip-id>" }`, ověří origin a zavolá php-core vlastními serverovými credentials.
2. `POST /transport/v1/trips/:id/tracking` ověří tenant, datum a schopnost adaptéru `realtime`. Vrací `status: available`, URL WebSocketu, podepsaný ticket a `expires_at`, nebo `unsupported`/`disabled`. Ticket je platný 15 minut, pouze pro daný tenant a konkrétní spoj s datem.
3. Prohlížeč otevře WebSocket a pošle `{ "type": "subscribe", "ticket": "..." }`. Ticket se neposílá v URL ani v logu. Každých 20 sekund posílá `{ "type": "ping" }`.
4. `TrackingHubService` sdílí jeden odběr podle `tenant + trip-id`. Gateway neblokujícím HttpModule klientem volá `GET /transport/v1/trips/:id/observation`. Tento endpoint používá běžný ResourceService, tenant, kvóty, circuit breaker a existující adaptéry. API klíč i tenant host pocházejí výhradně ze serverové konfigurace.
5. Odběratelé dostávají `{ "type": "observation", "trip": "...", "data": { "status": "live", "position": { "lat": 50.1, "lon": 14.4 }, "observed_at": "...", "valid_until": "...", "delay_seconds": 480, "cancelled": false } }`.
6. Gateway neuchovává poslední polohu pro nové odběratele. React uchovává aktuální pozorování pouze po dobu otevřeného detailu. Po nejvýše 30 sekundách od času pozorování se poloha i živý badge odstraní, i když síť úplně zamrzne. Zavření detailu nebo skrytí stránky odpojí odběry. Obnovení stránky provede nové ověření.

Odpovědi mají `Cache-Control: no-store`. Neznámé zpoždění není nula a nezobrazuje se jako „jede včas“. Zastaralá poloha nemá katalogový fallback. Endpoint `/realtime` zůstává zpětně kompatibilní; normalizovaný `/observation` je určen gateway.

## Podpora poskytovatelů

- **Golemio/PID:** existující adaptér poskytuje GPS a aktuální zpoždění. Před vydáním polohy ověřuje datum a začátek konkrétní jízdy proti online detailu, příznak tracking, rozsah souřadnic a čas měření. Živá komunikace a toto ověření byly zkontrolovány proti skutečnému API; tajný klíč ani polohy se při kontrole neukládaly.
- **Spojenka:** poskytuje vyhledávání a statický detail; současný adaptér nemá doložené propojení každé instance spoje na GPS zdroj. Vrací `unsupported`. Neodvozujeme vozidlo z čísla linky. Totéž platí pro ostatní adaptéry bez capability `realtime`.
- Nový GPS zdroj implementuje běžný `ResourceProvider` pro `realtime` a případné explicitní mapování identity. Gateway ani frontend nemusí znát jeho doménu či autentizaci. Adaptér musí dodat ověřené měření s timestampem. Samotný geografický překryv nestačí.
- Aktuálně implementovaný Golemio transport je REST. Gateway ho volá ve sdíleném intervalu; spojení gateway → prohlížeč je WebSocket. Nativní upstream WebSocket/SSE zatím není implementován. Jeho přidání patří do HttpModule a konkrétní integrace, ne do browsera.

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
