# Transport: PHP gateway

Před změnou čti `README.md`, `ARCHITECTURE.md` a root `AGENTS.md`.
Uživatel 3. 10. 2026 výslovně autorizoval odstranění PHP dopravní implementace.

1. PHP obsahuje pouze bezpečnostní/tenant hranici a transparentní gateway do Javy.
   Nepřidávej sem adaptéry dopravců, katalogy, GTFS/OSM transformace, importy,
   plánování, výpočty zpoždění, SQL fallback ani transportový WebSocket server.
2. Dopravní změny patří do [Java projektů](../../../../../java/AGENTS.md).
   Nový zdroj/země vyžaduje doloženou licenci a všechny dostupné schopnosti;
   částečné pokrytí se nesmí prezentovat jako kompletní. Ověř Java implementaci,
   nikoli existenci presetu. Odstranění PHP nepotvrzuje plnou funkční paritu.
3. Zachovej interní klíč a pevný tenant; SQL rate limiter zde nepoužívej. Java URL a token jsou
   pouze serverové; konfiguraci nelze zvolit browserem. Nenakonfigurovaný tenant
   vrací chybu, nesmí se připojit ke grafu jiného tenantu.
4. Síť vede přes injektovaný `Http\Contracts\HttpClient` ze společného HttpModule.
   Udržuj explicitní route/method/query allowlist a konečné časové/objemové limity.
   Administrace, build a synchronizace Javy nejsou veřejné gateway endpointy.
5. Dopravní JSON předávej beze změny, včetně plánovaných/očekávaných časů,
   metadata, statusů a `Retry-After`. Neplatné upstream odpovědi nepublikuj surové.
   Nepersistuj GPS uživatelů ani vozidel. Nastav `no-store`.
6. Staré TRAM SQL skripty jsou odstraněné; existující DB data se nemažou.
   Gateway nesmí používat SQL, Database ani SQL RateLimiter, ani pro počítadla.
   Testy gateway běží bez DB a explicitně zakazují databázový přístup.
7. Spusť `scripts/test-java-gateway.sh`, `scripts/test-http.sh`, PHP lint a diff check.
   Testy gateway patří do `Gateway/tests`; rozliš fixture a skutečnou Java službu.
   Dodržuj Graphify z root pravidel a aktualizuj dokumentaci kontraktu/nasazení.
