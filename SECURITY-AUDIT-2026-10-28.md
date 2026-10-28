# PHP core — opravy a přehodnocení auditu, 28. 10. 2026

## Body 1 a 2: oprava závěru auditu

Kontrola v `InternalAuth::check()` porovnává `X-Internal-Key` s neveřejným
`INTERNAL_API_KEY` přes `hash_equals()`. Nejde o hash uživatele ani o token
vlastníka objednávky. `Request::resolveCode()` vybírá pouze povolený tenant
z `FRANCHISE_CODES`. Klíč je
společné serverové tajemství, nikoli samostatné ověření klíče pro každý tenant.
Odmítnutí chybějícího klíče a neznámého tenantu potvrzují regresní sondy.

`API.md` již před auditem výslovně uvádělo **Admin or internal** pro
`POST /invoices` i obecné akce maileru; README navíc popisuje kontaktní
formulář Prasentace volající `/mailer/send` jen s interním klíčem.
Absence uživatelského Beareru u těchto důvěryhodných serverových operací
proto není sama o sobě chybou autorizace. CORE-01 a CORE-02 se stahují.
Původní syntetická měření byla správná, jejich klasifikace nikoli.
Opraveny jsou zavádějící komentáře a očekávání testů; chování těchto akcí
zůstalo zachované.

`OrderService::create()` podporuje nepřihlášený checkout: `user_id = null`
a kontakt/adresy ve snapshotu `order.customer`. Při vytvoření nové objednávky
se nekontroluje vlastník dosud neexistujícího účtu a nesmí se připojit účet
jen podle zadaného e-mailu. Faktura pak přebírá data z objednávky hosta.
API pro čtení existujících objednávek/faktur nadále vyžadují uživatele
a vlastníka/admina. Předání libovolného `order_id` z veřejného prohlížeče
je věcí autorizace konkrétní BFF; tento průchod ji neověřuje.

## Bod 3: opraveno — změna hesla a revokace tokenů

`AuthService::changePassword()` po ověření současného hesla volá novou
repository operaci `UserRepository::changePasswordAndRevokeTokens()`:

- Jedna transakce aktualizuje heslo a odstraní všechny `user_token` daného
  uživatele. Chyba při odstranění vrátí zpět i heslo.
- Zápis je podmíněn tenantem, aktivním/neodstraněným účtem a přesnou
  původní hodnotou hashe použitou při ověření. Zastaralý souběžný požadavek
  nemůže přepsat již změněné heslo; HTTP služba vrací 409.
- Ruší se také token aktuálního klienta. Odpověď zůstává 200 se stávajícím
  tělem; před dalším autorizovaným požadavkem je nutné nové přihlášení.
  BFF, která po změně hned ukládá profil se starým tokenem, potřebuje tento
  nový stav zohlednit. Frontendové BFF nebyly v tomto zadání upravované.

Testy ověřují nové heslo, odmítnutí obou předchozích tokenů skutečným
repository/Auth, zachování relace jiného uživatele, chybné původní heslo,
tenant, blokovaný/smazaný účet, rollback po selhání revokace a ochranu proti
zastaralému zápisu. Jde o skutečné SQL a transakce v dočasné SQLite paměti;
nejde o integrační ani zátěžové ověření zamykání produkčního MySQL nebo
paralelního přihlášení. Již běžící autorizovaný požadavek se zpětně neruší.

## Bod 4: opraveno — střídání e-mailů nepřekoná IP limit

Kontakt a newsletter nyní každý vedle původního email/IP limitu kontrolují
samostatnou IP: pět pokusů za hodinové okno v daném tenantu a akci.
`RateLimiter` dál ukládá jen hash subjektu a používá sdílenou DB tabulku.
Nevzniká nová tabulka ani migrace. Podvržená `X-Forwarded-For` se nepoužívá.

Testy skutečných handlerů ověřují šest stejných i různých e-mailů ze stejné
IP: jen pět fake odeslání, pak 429. Různé IP mohou pokračovat nezávisle.
SMTP je nahrazené a při dosažení prvního mailu se test zastaví; žádný mail
neodchází. Počítadla RateLimiteru mají paměťovou DB náhradu.

IP je `REMOTE_ADDR`. Za BFF může jít o společnou odchozí IP pro všechny
uživatele, takže tento limit sdílejí. Obnovu skutečné klientské IP smí dělat
jen ověřená infrastruktura; libovolná hlavička by opravu znovu obešla.
Nejde o nový globální/SMTP souběhový limit. Obecný `/mailer/send` těmto
limitům veřejných formulářů nepodléhá.

## Bod 5: výslovně ponecháno

Podoba ukládání a vydávání Bearer tokenů zůstala beze změny. Sonda nadále
zaznamenává raw token v náhradním DB insertu a uvádí `ACCEPTED_RISK` podle
rozhodnutí uživatele; netváří se, že se tokeny nově hashují.

## Opakování a evidence

```sh
php8.2 -d allow_url_fopen=0 tests/security/hardening-regression.php
php8.2 -d allow_url_fopen=0 tests/security/deep-hostile.audit.php
php8.2 tests/test_internal_auth.php
```

- Cílená regresní sada: 22 kontrol PASS. Potřebuje `pdo_sqlite`.
- Aktualizovaná hlubší sonda: 14 PASS a 1 ACCEPTED_RISK, bez FAIL/BLOCKED.
  Přibyla kontrola vytvoření faktury bez interního klíče.
- 86 kontrol interní autentizace a 137 základních sond prošlo.
- Celá `scripts/test-http.sh` včetně nové regresní sady prošla přes PHP 8.2.
  Sdílený test bootstrap měl předem nahrazenou DB konfiguraci; vlastní HTTP
  fixture běžely pouze na loopbacku. PHP lint a `git diff --check` prošly.
- [JSON evidence](tests/security/deep-hostile-2026-10-28.results.json).

Bez aplikačního bootstrapu, skutečných credentials, produkční DB, SMTP,
commitu, push nebo nasazení. Historické důkazy z 27. 10. zůstávají zachované.
