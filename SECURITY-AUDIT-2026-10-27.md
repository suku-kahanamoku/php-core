# Nepřátelský audit PHP core — 27. 10. 2026

> **Aktualizace 28. 10. 2026:** CORE-01 a CORE-02 byly chybně označené jako
> porušení autorizace. `API.md` už deklarovalo „Admin or internal“ pro mailer
> i vytvoření faktury; platný interní serverový klíč je pro tyto operace
> zamýšleným oprávněním. Tyto nálezy se stahují. CORE-03 a CORE-04 jsou nyní
> opravené a otestované, CORE-05 uživatel výslovně ponechal beze změny.
> Níže je historický stav před opravami. Aktuální závěr a limity ověření:
> [opravy a přehodnocení z 28. 10.](SECURITY-AUDIT-2026-10-28.md).

Hlubší lokální průchod potvrdil **pět skupin problémů**. Nejdůležitější jsou
chybějící uživatelská autorizace maileru a přímého vystavení faktury.
**Platný interní API klíč je u těchto dvou cest stále podmínkou**; nejde o
prokázané obejití InternalAuth ani o ověření veřejného produkčního útoku.

Předchozí PASS z 26. 10. se týkal pouze základních InternalAuth/QueryPolicy
kontrol. Neověřoval tyto konkrétní API akce. Tento report jej v daném rozsahu
rozšiřuje a workspace přehled již označuje PHP core jako FINDINGS.

## Důkazy a rozsah

- Nových 14 sond: **6 PASS, 8 FAIL, 0 chyb testovacího prostředí**.
  Osm porušených očekávání se sdružuje do pěti problémů, nikoli osmi
  nezávislých zranitelností.
- Skutečný `Request`, `InternalAuthMiddleware`, `Router`, `MailerApi`,
  `InvoiceApi` a doménové služby; závislosti jsou vloženy bez konstrukce
  provozního kontejneru. Repozitáře a mailová služba mají paměťové náhrady.
- Žádný aplikační bootstrap ani načtení skutečného `.env`, žádné PDO,
  skutečný SMTP, skutečné soukromé dokumenty, PDF render ani produkční URL.
  Jediná příloha je nový syntetický soubor v dočasném adresáři.
- PHP 8.2.35; všechny HTTP ukončující `Response` metody běží v oddělených
  CLI procesech. Fakturová sonda končí vlastní výjimkou na hranici fake
  zápisu. Její výchozí CLI status 200 není tvrzení o konečné HTTP odpovědi
  skutečného vytvoření faktury.
- Mimo nové sondy prošlo 137 původních základních kontrol, 86 kontrol
  interní autentizace a existující `scripts/test-http.sh` přes PHP 8.2.
  Provozní DB konfigurace byla u sdíleného test bootstrapu předem nahrazena.
  Jeden úvodní běh časové HTTP aserce `shared deadline includes waiting jobs`
  selhal; samostatné opakování i následný úplný běh prošly. Nebyl proto
  zařazen mezi reprodukované bezpečnostní nálezy.

## CORE-01 — P1: admin mailové akce nemají kontrolu identity a role

`MailerApi::registerRoutes()` registruje `POST /send`, `GET /test` a
`GET /list`, ale jejich handlery nevolají `Auth::requireRole('admin')` ani
jinou uživatelskou autorizaci. `MailerService` rovněž žádnou roli neověřuje.
Komentář v souboru sám o sobě oprávnění nevynucuje.

Reprodukce přes skutečný router a interní middleware:

| Sonda | Stav / efekt | Auth kontroly |
| --- | --- | --- |
| Bez interního klíče, bez Beareru | 401, žádný mail | 0 |
| Platný interní klíč, bez Beareru, `POST /send` | 200, jeden fake mail a jedna syntetická privátní příloha | 0 |
| Platný klíč, neadmin kontext, `POST /send` | 200, jeden fake mail | 0 |
| Platný klíč, admin kontext | 200, kontrolní fake mail | 0 |
| Platný klíč, bez Beareru, `GET /test` | 200, jeden fake testovací mail | 0 |

Dopad: volající s interním klíčem může využít funkci určenou administrátorovi
a předat známou cestu k souboru téhož tenantu jako přílohu. Ochrana proti
příloze mimo tenant root existuje; neřeší však uživatelské oprávnění k souboru.
Únik interního klíče ani skutečný cizí soubor nebyly vyhledávány.

**Návrh opravy:** na admin handlerech vynutit čerstvou uživatelskou identitu
a roli admin před zpracováním příloh/odesláním. Přílohy vybírat přes evidenci
souborů s autorizací a tenant scopingem. Testovací odesílání změnit z GET
na POST. Veřejný kontaktní formulář oddělit od obecného admin odesílání.
Zachovat použití společného `HttpModule`/`MailClient`.

Kód: [MailerApi.php](src/Modules/Mailer/MailerApi.php),
[MailerService.php](src/Modules/Mailer/MailerService.php).

## CORE-02 — P1: vytvoření faktury neověřuje oprávnění k objednávce

`InvoiceApi::create()` validuje jen `order_id` a zavolá službu.
`InvoiceService::create()` načte objednávku, zákazníka a připraví zápis
faktury bez `require`, `requireRole` nebo porovnání vlastníka objednávky.

S platným interním klíčem dosáhne stejného fake zápisu jak host bez Beareru,
tak uživatel 1 nad objednávkou uživatele 22. Oba záznamy jsou ve stejném
syntetickém tenantu. Počet auth kontrol je nula. Kontrolní admin sonda
rovněž dosáhne fake zápisu, takže závěr není způsobený nefunkční cestou.

Dopad: neautorizované vyvolání vystavení faktury pro objednávku jiného
uživatele v daném tenantu. Test nepersistuje fakturu, negeneruje PDF,
nezkouší duplicate race ani nepředstírá únik kompletní skutečné faktury.

**Návrh opravy:** v přímém HTTP endpointu vyžadovat deklarovanou admin roli
před voláním služby. Pokud se má podporovat vlastník objednávky, definovat
to výslovně a porovnat skutečného vlastníka ještě před zápisem/číslováním.
Interní serverové vystavování musí mít samostatný důvěryhodný kontrakt;
neřešit chybějící kontrolu tím, že se všechny volající považují za admina.

Kód: [InvoiceApi.php](src/Modules/Invoice/InvoiceApi.php),
[InvoiceService.php](src/Modules/Invoice/InvoiceService.php).

## CORE-03 — P2: změna hesla nezneplatní existující relace

`AuthService::changePassword()` ověří staré heslo a uloží nové, ale
nevolá odstranění tokenů ani nevydává náhradní relaci. Nová sonda naměřila
jednu aktualizaci hesla, nula revokací a přijetí původní relace skutečnou
`Auth::check()` nad paměťovým token repository.

Dopad: již získaný platný Bearer token zůstává použitelný i po běžné změně
hesla, dokud sám neexpiruje nebo není jinak odvolán. Nejde o tvrzení, že
útočník tento token v auditu získal. Reset přes `consumeAndChangePassword`
je jiná cesta a není tímto nálezem označený za chybný.

**Návrh opravy:** změnu hesla spojit transakčně s revokací předchozích
tokenů. Podle zvoleného UX odhlásit všechny relace nebo vydat novou
aktuálnímu klientovi a odstranit ostatní. Otestovat i rollback a paralelní
požadavky. [OWASP doporučuje obnovu relace při změně hesla.](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)

Kód: [AuthService.php](src/Modules/Auth/AuthService.php),
[UserTokenRepository.php](src/Modules/Auth/UserTokenRepository.php).

## CORE-04 — P2: kombinovaný email/IP limit není limit jedné IP

Veřejný kontaktní handler vytváří bucket z `email|REMOTE_ADDR` s limitem
5 za hodinu. `RateLimiter` pro jednotlivý bucket funguje; střídání e-mailu
však vytváří nové buckety. Stejný vzor používá newsletter.

Sonda šesti identických e-mailů ze stejné IP skončila správně 429 po pěti
fake mail efektech. Šest různých e-mailů ze stejné IP ve stejném okně
vytvořilo šest bucketů a dosáhlo šesti efektů. Sonda kontaktu skončí vždy
u prvního fake admin mailu, neodesílá zákaznické potvrzení a nevytváří
žádný skutečný síťový provoz.

Dopad: deklarovaná ochrana proti mailovému spamu není nezávislá na
útočníkem zvolené adrese. Služba interní klíč nadále vyžaduje; veřejná
nasazená BFF, její CAPTCHA a WAF nebyly ověřovány. Nejde o obcházení
samotného pětipokusového limitu pro shodnou kombinaci email/IP.

**Návrh opravy:** zachovat kombinovaný limit a přidat samostatný IP limit,
tenantový/global rozpočet i omezení souběhu/počtu SMTP úloh. U IP za proxy
vycházet jen z důvěryhodné infrastruktury. Limity volit s ohledem na NAT;
pouhé snížení limitu jednotlivého e-mailu tento problém neřeší.

Kód: [MailerApi.php](src/Modules/Mailer/MailerApi.php),
[RateLimiter.php](src/Utils/RateLimiter.php).

## CORE-05 — P3: Bearer token je v DB přímo použitelný

Skutečný `UserTokenRepository::create()` předá do insertu původní token.
Paměťová DB sonda potvrdila jeho nezměněnou hodnotu; žádný skutečný token
ani tabulka nebyly čtené. Toto je slabina ochrany uložených credentials,
ne reprodukovaný způsob přístupu do databáze.

Dopad: případný čtecí únik tabulky `user_token` nebo zálohy obsahuje
credentials použitelné do expirace. Hash hesla tento dopad neomezuje.

**Návrh opravy:** token uživateli vrátit pouze při vydání a v DB ukládat
hash/verifier, podle kterého probíhá ověření. Naplánovat kompatibilní
migraci a rotaci současných tokenů, včetně logout/reset cest. Chránit
zálohy a nikdy nelogovat tokeny.
[OWASP popisuje jednosměrné ukládání session verifierů.](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)

Kód: [UserTokenRepository.php](src/Modules/Auth/UserTokenRepository.php).

## Co se naopak v kontrolovaném rozsahu nepodařilo obejít

- Interní klíč: chybějící či chybný klíč je odmítnut před mail efektem.
- Čtení cizího uživatelského profilu běžným uživatelem skončilo 404.
- Vlastní PATCH profilu neumožnil podstrčit `role_id`, `status` ani `email`;
  zapsal se jen povolený `first_name`, odpověď nevrátila password hash.
- Původní povinné query podmínky a projekce nepřijaly nepovolené sloupce.
- Kontrolní stejné email/IP omezení správně dosáhlo 429.

Neznamená to, že byly ověřeny všechny CRUD moduly, všechny SQL dotazy,
OAuth poskytovatelé, MySQL transakce, uploader, OpenAI nástroje nebo
prohlížečová CSRF ochrana souvisejících webů. PHP core používá Bearer
autentizaci a má jinou CSRF hranici než cookie BFF. Zvlášť nebyla ověřována
produkční dostupnost, konfigurace webserveru/WAF nebo síťová zátěž.

## Opakování

```sh
php8.2 -d allow_url_fopen=0 tests/security/deep-hostile.audit.php
```

Exit 1 zde znamená reprodukované nálezy, exit 2 chybu samotné sondy.
Skript je mimo běžnou regresní sadu a nijak neotevírá běžící databázi.
Workspace runner nyní spouští i tuto hlubší sadu:

```sh
node ../../security-audit/run.mjs --project php/php-core
```

[Nové sondy](tests/security/deep-hostile.audit.php) ·
[JSON důkazy](tests/security/deep-hostile-2026-10-27.results.json) ·
[Přehled všech projektů](../../security-audit/README.md)

Přidány pouze sondy a dokumentace. Aplikační opravy, migrace, nasazení,
commit ani push nebyly provedeny.
