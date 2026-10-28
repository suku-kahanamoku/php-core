# Nepřátelský audit php/php-core — 26. 10. 2026

Výsledek diagnostiky: **PASS**. Běh dokončen: 2026-10-28T09:09:55.605Z.

PASS znamená splnění vybraných lokálních invariantů, nikoli bezpečnost celé aplikace. FINDINGS znamená reprodukované porušení očekávání; závažnost závisí na uvedených podmínkách. BLOCKED znamená chybu prostředí/harnessu a vyžaduje nápravu před hodnocením.

## Ověřený rozsah

Aktualizace 28. 10. 2026: skutečný Router/InternalAuth/API s náhradním SMTP; změna hesla a revokace přes skutečné repository a transakce v dočasné SQLite paměti. 15 hlubších sond a 22 cílených regresních kontrol, vedle původních InternalAuth/QueryPolicy. Bez .env a provozní DB.

Testy ani tento report nemění aplikační oprávnění, databázi běžící aplikace, produkci, nasazení nebo skutečné přístupové údaje. PHP Influence používá výhradně novou dočasnou SQLite DB.

## Nálezy a návrhy oprav

V tomto omezeném rozsahu nebylo reprodukováno porušení kontrolovaných invariantů. Další oblast není automaticky ověřená pouze proto, že zde vyšla PASS.

## Další krok a limity

Body CORE-01/02 staženy: API.md už před auditem deklarovalo Admin or internal pro mailer a vytvoření faktury; uživatelský Bearer zde není podmínkou. CORE-03 opraven atomickou změnou hesla a revokací všech relací (nutné nové přihlášení); CORE-04 samostatným IP limitem kontaktu/newsletteru 5/h. CORE-05, raw Bearer v DB, výslovně ponechán uživatelem a v sondě značen ACCEPTED_RISK. PASS proto neznamená odstranění tohoto rizika. Za BFF se REMOTE_ADDR může sdílet; obecný /mailer/send není těmito limity pokrytý. BFF, produkční MySQL a paralelní login nebyly ověřené. Podrobnosti: [aktualizace 28. 10.](SECURITY-AUDIT-2026-10-28.md).

Návrhy jsou ručně posouzený snapshot tohoto auditu. Budoucí změny výsledků vyžadují nové posouzení; samotný opakovaný běh nemůže automaticky potvrdit opravu všech dřívějších nálezů. CORE-03/04 byly opravené a regresně ověřené 28. 10. 2026; CORE-01/02 stažené a CORE-05 výslovně ponechaný.

## Opakování

Z kořene tohoto projektu:

```sh
node ../../security-audit/run.mjs --project php/php-core
```

Přímé příkazy tohoto běhu:

```text
"php8.2" "-d" "allow_url_fopen=0" "tests/security/hostile.audit.php"
"php8.2" "-d" "allow_url_fopen=0" "tests/security/deep-hostile.audit.php"
"php8.2" "-d" "allow_url_fopen=0" "tests/security/hardening-regression.php"
```

[Výstup testů](../../security-audit/results/php--php-core.log) · [Celý workspace audit](../../security-audit/README.md)

Exit 0 = kontroly prošly, 1 = diagnostické nálezy, 2 = blokující chyba běhu. Nové diagnostiky leží mimo běžné výchozí testovací globy. Nic nenasazovat jen kvůli spuštění testu.
