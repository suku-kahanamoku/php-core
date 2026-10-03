# TRAM: realtime z Java služby

PHP transportové sledování, hub a serverový CLI proces byly odstraněny
3. 10. 2026. PHP pouze předá autentizované REST požadavky přes HttpModule do
Java API; Java vlastní realtime adaptéry, RAM pozorování, tickety a WebSocket.

1. Statický detail načte `GET /transport/v1/trips/:id` přes PHP gateway.
2. Otevření dialogu souběžně požádá o
   `GET /transport/v1/trips/:id/observation` pro okamžitou polohu a zpoždění.
3. `POST /transport/v1/trips/:id/tracking` vydá Java ticket a adresu socketu.
   Browser multiplexuje odběry přes jeden WebSocket; PHP není WS server.
4. React aktualizuje malé živé části (badge, marker, riziko návaznosti).
   Statické řádky, názvy, plánované časy a legendy zůstávají beze změn.
   Badge se nezobrazuje pro nulové či neznámé zpoždění. Poslední jednoznačný
   marker zůstává v paměti otevřeného dialogu, při chybě se nepřesouvá podle hodin.

Poloha a zpoždění jsou nezávislé údaje a mají původní čas měření a platnost.
Bez prvního ověřeného GPS vzorku nelze červený bod pravdivě zobrazit. Tracking
není příslib GPS každého dopravce. Očekávané časy slouží Java plánování a
posouzení návazností; frontend zobrazuje plánované časy a upozornění na riziko.
Polohy lidí a vozidel se neukládají do SQL, souborů ani browser storage.

Konfigurace a wire kontrakt:
[Java API](../../../java/OTP/API.md), [OTP README](../../../java/OTP/README.md),
[Java architektura](../../../java/ARCHITECTURE.md) a
[PHP gateway](../src/Modules/Transport/README.md).
Java WS proxy, soukromý API token, upstream credentials a realtime role se
konfigurují v Javě. Původní PHP `TRANSPORT_TRACKING_*` nastavení a
`bin/transport-tracking.php` již neexistují; jejich supervisor/cron položky
odstraňte při nasazení. R2/Cloudflare nasazení není tímto refaktorem provedeno.
