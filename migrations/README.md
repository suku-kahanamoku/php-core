# Schémata a výchozí data

SQL je určené pro MySQL 8 a lze jej vložit do Admineru nebo spustit klientem
`mysql`. Nevyžaduje `SOURCE`, `DELIMITER` ani oprávnění vytvářet procedury.

## Instalace a opakované doplnění struktury

1. Spustit `schema.sql` – 28 společných tabulek, žádná data.
2. Spustit `<projekt>_schema.sql` podle tabulky níže.
3. Volitelně spustit odpovídající `<projekt>_seed.sql`.

| Projekt | Struktura po společném schématu | Výchozí data |
| --- | --- | --- |
| Etymolog | `etymolog_schema.sql`, 16 tabulek | `etymolog_seed.sql`, 2 role a 30 synchronizačních úloh |
| TRAM | `tram_schema.sql`, 15 tabulek `transport_*` | `tram_seed.sql`, 12 druhů dopravy; poskytovatele nastavuje `scripts/transport-configure.php` |
| SRY | `sry_schema.sql`, 16 tabulek | `sry_seed.sql`, role a kategorie |
| Zoo | `zoo_schema.sql`, používá společné tabulky | `zoo_seed.sql`, účty, katalog, profily a vazby |
| FAnn | `fann_schema.sql`, používá společné tabulky | `fann_seed.sql`, finální tenant `fann`, katalog, profily a vazby |
| Zaječí | `zajeci_schema.sql`, používá společné tabulky | `zajeci_seed.sql`, původní výchozí obsah |

`schema_seed.sql` obsahuje stejná data jako `zajeci_seed.sql`, oddělená od původního
`schema.sql`. Stačí spustit jeden z nich; ani použití obou nevytvoří duplicity.
Zoo, FAnn a Zaječí nemají samostatné tabulky. Jejich schéma proto pouze dokumentuje
závislost na společném schématu a nastavuje kódování; nekopíruje společné definice.

Příklad Etymologu:

```sh
mysql -u php_core -p php_core < migrations/schema.sql
mysql -u php_core -p php_core < migrations/etymolog_schema.sql
mysql -u php_core -p php_core < migrations/etymolog_seed.sql
```

Schémata vytvářejí chybějící tabulky a podle `information_schema` doplňují chybějící
sloupce, indexy a vazby. Nejprve vzniknou tabulky bez FK, potom sloupce, indexy a
nakonec FK. Existující tabulky ani jejich obsah se nemažou. Dvě historické změny
Etymologu bezpečně uvolňují `name_id` na NULL a rozšiřují krátký kurzor na 2048 znaků.
Index `uq_etymolog_import` zahrnuje také `external_id`, takže více Wikidata QID
může odkazovat na stejné jméno. Schéma bezpečně rozšíří i dřívější třísloupcový
index bez mazání záznamů.
Ostatní existující definice sloupců se nepřepisují; například vlastní širší sloupec
se nezkrátí. Nejde o nástroj na automatickou opravu libovolného nesouladu schématu.
Konfliktní data při přidání unikátního indexu nebo FK způsobí chybu, nikoliv jejich
odstranění. Schémata spouštějte postupně, nikoli souběžně.

Seedy obsahují pouze data. Řádky hledají podle tenantových přirozených klíčů a vazby
překládají na skutečná ID. Nedělají reset hesel, nepřepisují upravené produkty,
neobnovují smazané záznamy a nemění stav ani kurzory existujících synchronizací.
Etymolog seed žádnou synchronizaci nespouští. Produktové demo účty a katalog se
nevkládají samotným schématem; vybírejte jen seed projektu, který chcete založit.

## Úplné vyčištění obsahu Etymologu

`etymolog_reset_content.sql` je samostatný, destruktivní údržbový skript pro tenant
`etymolog`. Není součástí instalace ani seedování. V Admineru jej spusťte celý
v jednom spojení až po dokončení běžící synchronizace.

Smaže všechna jména, příjmení, výklady, příběhy, varianty, statistiky, kalendáře,
kalendářní dny, citace a vazby, včetně ručního obsahu, konceptů a logicky smazaných
záznamů. Odstraní také importní snapshoty a historii synchronizačních běhů.
Zachová uživatele, role, prameny s licencemi (`etymolog_source`), nastavení úloh
(`etymolog_sync_job`) a všechny ostatní tenanty. Kurzory, splatnost a poslední stav
úloh vynuluje, ale nezapíná vypnuté ani neobnovuje smazané úlohy. Denní pojistka
cronu (`etymolog_sync_schedule`) zůstává zachována.

Výsledek `RESET` znamená dokončený reset. Při `SKIPPED_BUSY` se nezměnilo nic:
běží zápis nebo synchronizace čeká/probíhá. Vyčkejte na její ukončení a opakujte
skript. Při SQL chybě zastavte provádění, proveďte `ROLLBACK` a ukončete spojení.
Nepoužívejte režim pokračování po chybách (`mysql --force`). Skript používá
transakci a stejné tenantové zámky jako aplikace, cizí klíče nevypíná.

Po resetu můžete kliknout na **Spustit synchronizaci**. Zapnuté úlohy začnou od
první dávky a další dávky budou pokračovat obvyklým způsobem. Importované texty
budou opět koncepty. Záznamy dostanou nová ID, původní odkazy na detail přestanou
fungovat. Skript sám synchronizaci nespouští.

## Údržba a ověření

Definice `CREATE TABLE` jsou na začátku schémat. Řádky `-- deferred CONSTRAINT`
obsahují definice vazeb, které se přidávají až po doplnění všech sloupců.
Po změně definic přegenerovat opakovatelnou část:

```sh
python3 scripts/build-schemas.py
python3 scripts/build-schemas.py --check
bash scripts/test-schema.sh
bash scripts/test-etymolog.sh
bash scripts/test-transport.sh
bash scripts/test-sry.sh
```

Testy používají vlastní dočasné MySQL bez síťového portu, nikdy aplikační DB.
Kontrolují čistou instalaci, opakované provedení, cizí ID, doplnění chybějících
sloupců/indexů/FK a zachování hesel, obsahu, smazaných záznamů i kurzorů.

Součástí `test-schema.sh` je také reset Etymologu na testovacích datech: kontroluje
zachování účtů, pramenů, nastavení úloh a druhého tenantu, aktivní zámky a dávky,
rollback při chybě cizího klíče a opakované spuštění.
