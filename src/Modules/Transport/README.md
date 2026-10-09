# TRAM: přihlášení a autorizace administrátora

Astro dopravní API volá přímo Javu přes `CoreModule/server/java-tram.ts`.
PHP dopravní gateway `/api/transport` byla odstraněna 9. 10. 2026 a Apache
vrací pro původní cesty 410. Katalogy, GTFS/OSM, grafy, plánování, cache cest,
realtime a tracking vlastní [Java služby](../../../../../java-tram/README.md).

V PHP zůstávají společné Auth účty a jediná autorizační hranice:
`GET/POST /api/transport-admin/online-planners`. Interní klíč, pevný tenant
a Bearer role `admin` se ověří před voláním pevné Java `/admin/online-planners`
cesty. Odpověď obsahuje pouze `enabled: boolean`; POST přijímá pouze toto pole.
Stav ukládá Java, PHP nemá dopravní repository, cache ani čítače.
Odpovědi mají `private, no-store`. Sync/build/deploy nejsou PHP endpointy.

Serverová konfigurace tohoto oprávnění:

```dotenv
TRANSPORT_JAVA_TENANT=tram
TRANSPORT_ONLINE_CONTROL_ENABLED=1
TRANSPORT_ONLINE_CONTROL_URL=https://java-router.example
TRANSPORT_ONLINE_CONTROL_TOKEN=<private-java-admin-token>
```

`FRANCHISE_CODES` musí mapovat host na `tram`. URL a token pocházejí pouze
ze serveru. Nenakonfigurovaný nebo jiný tenant vrací 503, role se ověřuje
společným Auth. Síť vede přes `HttpModule::client()` s konečnými limity.
`TRANSPORT_JAVA_ENABLED`, `TRANSPORT_JAVA_URL`, `TRANSPORT_JAVA_TOKEN` už PHP
nepoužívá. Dopravní Java token si spravuje Astro ve své serverové konfiguraci.

`InternalAuthMiddleware` omezuje tenant `tram` na Auth a tuto admin hranici
před připojením k SQL. Obecné CRUD, mailer, soubory a AI API jsou pro něj
zakázané i s platným interním klíčem nebo Rokid klíčem. Ostatní tenanty
používají své stávající endpointy.

## Odstranění historické SQL databáze

[CLI čištění](../../../../scripts/cleanup-tram.php) standardně pouze vypíše
plán. Provedení vyžaduje `--apply`, přesný název DB a soukromou zálohu mimo
webový checkout. Viz [návod migrace](../../../../migrations/README.md#tram-po-přesunu-do-javy).
Odstraní 17 známých `transport_*` tabulek, TRAM číselníky a neautentizační
čítače. Zachová účty, role, relace, OAuth, reset hesla a autentizační limity.
Neznámé TRAM záznamy, SQL objekty, cizí tenant v transportní tabulce nebo
vnější FK čištění zastaví před první změnou. FK se při mazání nevypínají.

Kontroly: `bash scripts/test-transport-auth.sh`, `bash scripts/test-tram-cleanup.sh`,
`bash scripts/test-http.sh`, PHP lint a `git diff --check`.
Test migrace používá pouze jednorázovou MySQL; test autorizace používá HTTP mock.
