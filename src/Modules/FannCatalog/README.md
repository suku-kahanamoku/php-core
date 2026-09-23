# FAnnCatalog

Modul synchronizuje veřejný katalog `https://www.fann.cz/` do databázového
tenantu `fun`. Importuje šest hlavních kategorií FAnn a nejvýše 50 aktuálně
vypsaných produktových variant z každé kategorie. Pokud kategorie obsahuje méně
variant, uloží všechny dostupné.

## Ukládaná data

- kategorie používají anglické `syscode` i zobrazované názvy: `skincare`
  (`Skin Care`), `perfumes` (`Fragrances`), `makeup` (`Makeup`), `body-care`
  (`Body Care`), `accessories` (`Accessories`) a `gift-cards-and-vouchers`
  (`Gift Cards and Vouchers`);
- produkt je jednoznačný pomocí `FANN-{EAN}`, případně ID varianty;
- cena, dostupnost, značka, popis a obrázky pocházejí z Product JSON-LD;
- varianta, cílová skupina, složení vůně a ingredience se čtou z detailu;
- zdrojová URL, FAnn ID produktu/varianty a datum kontroly jsou v
  `product.data.catalog_source`;
- vazby kategorií se ukládají do `product_category` a duplicity mezi
  kategoriemi se nestahují ani nezakládají podruhé.

`stock_quantity = 1` znamená pouze veřejný stav `InStock`, nikoli skutečný počet
kusů ve skladu. `0` odpovídá jiné veřejné dostupnosti. Import nemaže produkty,
které z prvních 50 položek později zmizí, a při aktualizaci zachová cizí klíče v
`product.data` (například ručně doplněné výběrové atributy).

Starší klíče s prefixem `fann-` importér automaticky přejmenuje. Pokud už
existuje významově shodná kategorie (`perfumes` nebo `makeup`), převede na ni
produktové vazby a starší duplicitní kategorii odstraní.

## Spuštění

```bash
php8.2 scripts/import_fann_catalog.php --limit=50 --concurrency=4
```

Příkaz načte databázové připojení z `.env`. Je opakovatelný a aktualizuje
existující řádky podle tenantového SKU. Konkurence je záměrně omezena na 1–6
požadavků. Importér přijímá pouze HTTPS URL hostu `www.fann.cz`.

Pokud je zapnuté sémantické vyhledávání, po dokončeném importu se samostatně
aktualizuje produktový Vector Store:

```bash
php8.2 scripts/sync_openai_vector_store.php --tenant=fun
```

Import záměrně nevolá OpenAI sám. Katalog tak lze obnovit i při výpadku OpenAI
a synchronizaci bezpečně zopakovat později. Podrobnosti jsou v
[`../OpenAi/README.md`](../OpenAi/README.md).

## Test parseru

```bash
php8.2 src/Modules/FannCatalog/tests/FannCatalogParserTest.php
```

Test nepoužívá síť ani databázi a hlídá kategorie, produktové odkazy, UTF-8 a
mapování JSON-LD/detailových atributů.
