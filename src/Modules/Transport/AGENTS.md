# TRAM: pouze přihlášení a autorizace v PHP

Před změnou čti `README.md`, `ARCHITECTURE.md` a root `AGENTS.md`.
Uživatel 9. 10. 2026 výslovně požadoval odstranit veškeré TRAM SQL tabulky,
data a PHP dopravu s výjimkou přihlášení a autorizace.

1. Dopravní komunikace Astro vede přímo do Javy. Neobnovuj PHP gateway,
   adaptéry, GTFS/OSM, plánování, importy, cache, SQL fallback ani tracking.
2. Zůstává Auth a admin oprávnění `GET/POST /transport-admin/online-planners`.
   Zachovej interní klíč, pevný tenant, Bearer a role admin. Stav vlastní Java.
3. TransportModule nemá SQL. Auth SQL slouží jen účtům/relacím/oprávněním;
   neautentizační TRAM zápisy do obecných modulů blokuje middleware.
4. Pevný Java admin klient používá injektovaný HttpClient, serverové URL/token,
   konečné limity a `private, no-store`. Nezveřejňuj obecnou proxy ani sync/build.
5. Historické DB čištění vlastní `Database/Maintenance/TramCleanupRepository`
   a CLI `scripts/cleanup-tram.php`. Před DROP kontroluje celý rozsah a vytvoří
   soukromou obnovitelnou zálohu mimo www. Zachovej data dalších tenantů.
6. Spusť `scripts/test-transport-auth.sh`, `scripts/test-tram-cleanup.sh`,
   `scripts/test-http.sh`, PHP lint a diff check. SQL testy jen v disposable DB.
   Použij Graphify z root pravidel a aktualizuj kontrakt/návod nasazení.
