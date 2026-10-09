# PHP autorizační hranice TRAM

Doprava: browser → Astro BFF → Java `/transport/v1/...`.
Přihlášení: browser → Astro BFF → PHP `/api/auth/...` → Auth SQL.
Administrace: browser → Astro BFF → PHP `/api/transport-admin/online-planners`
→ interní klíč + tenant + Auth admin → Java `/admin/online-planners`.

| Soubor | Odpovědnost |
| --- | --- |
| `TransportModule.php` | Sestavení jediného admin klienta pro explicitní tenant |
| `Admin/OnlinePlannerApi.php` | Povolené metody, validace a autorizace před síťovým voláním |
| `Admin/OnlinePlannerService.php` | Pevná Java cesta, soukromé credentials a výběr jediného veřejného pole |
| `Admin/OnlinePlannerException.php` | Bezpečné administrační chyby |
| `api/transport-admin/index.php` | Společný bootstrap a Auth ověření role `admin` |
| `src/Middleware/InternalAuthMiddleware.php` | TRAM přístup pouze do Auth/admin před vytvořením DB služeb |
| `scripts/cleanup-tram.php` | Explicitní CLI údržba historických SQL dat |
| `Database/Maintenance/TramCleanupRepository.php` | Kontrola schématu, soukromá záloha, transakční sdílené DELETE a FK pořadí DROP |

PHP veřejná gateway, dopravní adaptéry, SQL katalog, GTFS/OSM import,
plánování a tracking neexistují. Jediným TRAM SQL stavem je Auth včetně
bezpečnostních čítačů přihlášení. Online politika se ukládá v Java routeru.
Odstranění historického PHP samo nepotvrzuje plnou Java funkční paritu.

Konfigurace a kontroly: [README](README.md).
