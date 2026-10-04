# Architektura PHP gateway TRAM

Aktuální hranice od 3. 10. 2026: PHP přijímá autentizovaný požadavek,
předá jej Java API a vrátí odpověď. Dopravní výpočty a synchronizace jsou
v [Java projektech](../../../../../java-tram/ARCHITECTURE.md).

| Soubor | Odpovědnost |
| --- | --- |
| `api/transport/index.php` | Společný bootstrap, tenant a interní klíč; sestavení a dispatch routeru |
| `TransportModule.php` | Výslovné zapnutí Java gateway pro jediný nakonfigurovaný tenant; injekce HTTP kontraktu |
| `Gateway/JavaTransportApi.php` | Registrace povolených GET/POST cest a `no-store` odpovědi |
| `Gateway/JavaTransportService.php` | Pevný upstream, soukromý Bearer, síťové limity a transparentní JSON předání |
| `Gateway/JavaTransportException.php` | Bezpečné chyby konfigurace, cesty a upstream spojení |
| `Admin/LocalPipelineService.php` | Serverový klient explicitní fronty lokálních sync_build/deploy operací; bez veřejné route a bez dopravních výpočtů |
| `api/transport-admin/index.php`, `Admin/LocalPipelineApi.php` | Oddělená administrační hranice s interním klíčem a společným Auth ověřením role admin a tenantu před zařazením úlohy |

Gateway se k databázi vůbec nepřipojuje. Interní klíč a tenant ověřuje ze
serverové konfigurace; SQL rate limiter se zde nepoužívá. Nemá dopravní
repository, SQL cache, provider registry, importéry ani plánovač. PHP nemění
`scheduled_*`, `expected_*`, zpoždění, identity, metadata ani geometrie.
REST tracking vydává ticket z Javy; následné WebSocket spojení browseru běží
přímo proti adrese vrácené Java službou. PHP se jeho lifecycle neúčastní.

URL pochází pouze ze serverové konfigurace. Router nepublikuje administraci,
build/sync API ani libovolný proxy request. Chyba Javy nezapíná staré PHP
poskytovatele; jejich zdrojový kód byl odstraněn. Chybějící konfigurace vrací
503 a neplatná upstream obálka 502. Ostatní upstream statusy a `Retry-After`
se zachovávají.

Konfigurace, endpointy, limity a testy jsou v [README.md](README.md).
Rozdíly proti historické implementaci jsou v
[Java PARITY.md](../../../../../java-tram/PARITY.md); odstranění starého kódu
samo nedoplnilo chybějící schopnosti Javy. Staré TRAM SQL skripty byly odstraněny z projektu;
žádná existující aplikační tabulka ani data nebyla tímto krokem smazána.
