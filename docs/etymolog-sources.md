# Etymolog – ověřené prameny a stav synchronizace

Průzkum a živá kontrola: **28. 9. 2026**. „Veřejně dostupné“ samo o sobě
neznamená oprávnění převzít databázi. Níže rozlišujeme obsah, přístup, konkrétní
licenci a skutečně implementovaný import. Žádný provider negeneruje etymologii
z pouhé shody názvu ani neodvozuje národnost nositele jména.

## Zapojené zdroje

| Zdroj | Obsah | Licence | Implementace a rozsah |
|---|---|---|---|
| [Wikidata](https://www.wikidata.org/wiki/Wikidata:Licensing) | Katalog jmen, jazyk užití, alternativní popisky a tvrzení s dostupnými referencemi | CC0-1.0 | Existující `wikidata`; cs/sk/pl/uk/de/en, křestní jména a příjmení. Nejde o úplný odborný etymologický slovník. |
| [České Wikizdroje](https://cs.wikisource.org/wiki/Staré_pověsti_české_(1959)) | Pověsti a souvislosti s postavami/jmény | U vybraných kapitol ověřované PD old 70 | Existující `wikisource`; čtyři kurátorované kapitoly, drafty a návrhy vztahů. Další díla vyžadují konkrétní výběr a kontrolu licence. |
| [English Wiktionary](https://en.wiktionary.org/wiki/Wiktionary:Copyrights) | Etymologické odstavce pro jazykově zařazená jména a příjmení | CC-BY-SA-4.0; odkaz na historii autorů, revizi, licenci a popis úprav | Nový `wiktionary`; cs/sk/pl/uk/de/en přes jazykové kategorie. Výklad zůstává anglicky. Kategorie není zárukou přítomnosti etymologie; chybějící výklad se přeskočí. |
| [Ministerstwo Cyfryzacji / PESEL](https://dane.gov.pl/pl/dataset/1681) | Celostátní počty příjmení žijících osob v registru; mužská a ženská populace zvlášť | API metadat deklaruje CC0 1.0; kontrolujeme i případné dodatečné podmínky | Nový `poland-pesel`; úplné oficiální CSV, automatický výběr nejnovějšího národního vydání. Země PL, jazyk jména se neodvozuje. |
| [ČSÚ – dětská jména 2025](https://csu.gov.cz/produkty/viktorie-byla-vubec-poprve-nejoblibenejsi-jakub-prvenstvi-obhajil-tesne) | TOP 100 jmen narozených dětí v Česku za rok 2025, podle pohlaví | [CC BY 4.0](https://csu.gov.cz/podminky_pro_vyuzivani_a_dalsi_zverejnovani_statistickych_udaju_csu) | Nový `csu-baby-names`; ověřený XLSX, 201 řádků kvůli shodnému pořadí. Pevně vybrané vydání 2025, nové ročníky se přidají po ověření formátu a metodiky. |

### Wiktionary: původ slov, nikoli automaticky ověřený fakt

Příklad skutečně existující etymologie: [Novák](https://en.wiktionary.org/wiki/Novák).
Česká a anglická jazyková edice nejsou obsahově stejné; české heslo nemusí mít
etymologii, kterou anglická edice uvádí pro češtinu. Proto provider používá
`en.wiktionary.org`, ale jazyk zkoumaného jména vybírá zvlášť.

Technické rozhraní: MediaWiki Action API `query/categorymembers`,
`query/siteinfo` (`rightsinfo`) a `parse`. Kategorie příjmení, pro křestní jména
postupně mužská, ženská, unisex a obecná kategorie. Nepředstíráme rekurzivní
pokrytí libovolných podkategorií. Čteme aktuální revizi, ověřujeme členství
v kategorii a bereme pouze etymologickou skupinu se současným významem
`Proper noun` + `surname` / `given name` v požadovaném jazyce.

Výstup: draft `entry(type=etymology, certainty=unverified, language=en)`,
zdroj, citace a snapshot. Jméno má např. `language=cs`; to není jazyk textu.
Převzaté texty a jejich úpravy je při publikaci potřeba uvádět s atribucí,
licencí a splnit ShareAlike. Zdrojové reference jsou dostupné přes trvalý
odkaz na revizi; parser nepřevádí všechny odkazy slovníku na samostatné citace.
Žádné obrázky, výslovnostní audio ani celé heslo se nekopírují.

### PESEL: význam dat, verze a úplnost

[Metadata datové sady](https://api.dane.gov.pl/1.4/datasets/1681) obsahují
`license_name: CC0 1.0`. Poskytovatel uvádí vynechání zemřelých osob a příjmení
s jediným výskytem. Nezaměňovat s počtem občanů polské národnosti.

Při průzkumu byly nejnovější národní zdroje:

- [mužská populace, 20. 1. 2026, resource 1148808](https://api.dane.gov.pl/1.4/resources/1148808),
- [ženská populace, 20. 1. 2026, resource 1148811](https://api.dane.gov.pl/1.4/resources/1148811).

Provider nefixuje tyto identifikátory: při novém průchodu vybírá nejnovější
přesně odpovídající národní zdroj mezi posledními 20 zdroji sady 1681. Pokud jej
nenajde nebo metadata změní schéma, skončí chybou. Rozpracovaný průchod připne
resource ID a SHA-256 souboru do kurzoru. Nové vydání se vybere po dokončení
průchodu nebo explicitním admin resetu. Oprava CSV během průchodu způsobí chybu
`statistics_snapshot_changed`; nikdy se nespojí části různých souborů.

Tabulkové `/data` API při živé kontrole vykazovalo okno 10000 řádků, ačkoli
`meta.count` mužského zdroje bylo 408182. Provider proto používá **celé CSV**
a lokálně z něj bere další dávku. Každý běh znovu načte CSV (mužský export byl
cca 5,3 MB), bez trvalé souborové cache; toto je provozní náklad první verze.
Dávka nejvýše 500 záznamů, maximální soubor 25 MB. Soubor se nedekomprimuje ani
nestahuje na libovolnou URL: povolen je jen konkrétní host a cesta exportu.

Pohlaví je vlastnost statistického výskytu. Záznam zachovává přesné datum
`observed_on`, rok, `sex`, `measure=living_persons`, původní zápis a počet.
Mužské a ženské statistiky se nesčítají automaticky. Regionální exporty nejsou
v této verzi importované. Nové roční vydání má vlastní výskyty; historie
jednotlivých změn uvnitř stejného vydání se nearchivuje.

### ČSÚ: novorozenci jsou jiná populace než všichni nositelé jména

[Oficiální XLSX](https://csu.gov.cz/docs/107508/0a6170f4-bc53-7d35-afe2-3d5fcd0acb47/data_detska_jmena_top_100_cesko_2025.xlsx?version=1.0)
má listy `Chlapci`, `Dívky`, sloupce `Jméno`, `Počet`, `Pořadí`. Pořadí může
být interval (např. `100-101`); nedochází k jeho oříznutí. Jde o TOP 100,
nikoli úplný seznam všech narozených jmen. Ukládá se `measure=births`, rok 2025,
pohlaví a země CZ; `observed_on` zůstává null, protože jde o roční období.
Jazyk ani český původ jména se neodvozují z místa narození.

Na začátku každé dávky ověřujeme licenční odkaz na oficiální stránce podmínek.
XLSX se zpracovává lokálně přes PHP DOM/ZipArchive, s limity velikosti XML,
bez extrakce ZIP na disk, bez vzorců a bez síťových odkazů uvnitř sešitu.
Dávka připíná hash vydání stejně jako PESEL. Nové vydání jiné ročenky není
automaticky zaměněno za rok 2025.

## Další prozkoumané zdroje a konkrétní další krok

| Pramen | Přínos | Výsledek ověření / podmínka dalšího zapojení |
|---|---|---|
| [Kaikki.org](https://kaikki.org/dictionary/) a [Wiktextract](https://github.com/tatuylonen/wiktextract) | Strojově čitelné extrakty Wiktionary, včetně etymologie; vhodné pro větší dávky. | Sekundární cesta ke stejnému prameni, nikoli nezávislé potvrzení výkladu. Příští optimalizace: verzovaný lokální JSONL import a filtrace vlastních jmen. Licence parseru není licence extrahovaných textů; zachovat licenci Wiktionary. |
| [JÚĽŠ SAV – příjmení na Slovensku](https://www.juls.savba.sk/durco_priezviska.html) | Historické rozšíření příjmení, stav 1995. | Vyhledávací rozhraní a bibliografie jsou veřejné; hromadnou redistribuční licenci ani podporované exportní API jsem neověřil. Vyžádat licenci/export, nepřepisovat rok na současnost. |
| [Internetowy słownik nazwisk w Polsce, IJP PAN](https://nazwiska.ijppan.pl/info/oslowniku) | Odborné etymologie, historické zápisy a odkazy na literaturu. | Obsahově velmi vhodné. Veřejné vyhledávání není potvrzením práva na hromadné převzetí. Další krok: dohodnout dataset/API a licenci s vydavatelem. Není automaticky synchronizováno. |
| [DFD – Digitales Familiennamenwörterbuch Deutschlands](https://www.namenforschung.net/dfd/) | Odborné výklady a rozšíření německých příjmení. | Živý požadavek vrátil HTTP 403 a anti-bot výzvu; podmínky hromadného přebírání se nepodařilo potvrdit. Potřebujeme oficiální export a souhlas. Ochranu neobcházíme. |
| [DMNES](https://dmnes.org/about) | Jména doložená v evropských pramenech mezi lety 500–1600; užitečné historické varianty a citace. | Web uvádí autorská práva editorů; otevřenou redistribuční licenci/API jsem neověřil. Vhodný partner pro licencovaný import, zatím odkazy pro redaktora. |
| [ÚJČ – doporučené zdroje](https://ujc.cas.cz/cs/jazykova-poradna/elektronicke-zdroje-a-doporucena-literatura/), [Vokabulář](https://novy.vokabular.ujc.cas.cz/Home/Links) | Odborné a historické významy základových slov, literatura. | Užitečné k redakčnímu ověřování; nelze každé obecné slovo vydávat za etymologii stejně znějícího příjmení. Potřebujeme podmínky konkrétní databáze/exportu. |
| [ČRo Dvojka – příklad výkladu](https://dvojka.rozhlas.cz/dvoracek-8959923) | České odborné popularizační výklady a bibliografické odkazy. | [ČRo požaduje kontakt pro užití materiálů](https://informace.rozhlas.cz/podminky-uziti-obsahu-ceskeho-rozhlasu-8197077). Plné texty nesynchronizujeme. Možná budoucí spolupráce nebo odkazování podle jejich podmínek redistribuce. |
| [Registr Krameriů](https://registr.digitalniknihovna.cz/) | Staré knihy, časopisy, OCR a bibliografická metadata přes API jednotlivých knihoven. | API existuje, ale licence je potřeba posoudit u konkrétního dokumentu a vrstvy dat. Režim DNNT ani veřejné zobrazení neznamenají volné kopírování. Další krok: kurátorovaný seznam konkrétních volných titulů. |
| [Behind the Name – přístup k datům](https://www.behindthename.com/api/) | Jména, pohlaví, užití a příbuzné podoby. | Free API požaduje klíč a uvádí gender/usage/random/synonyms, nikoli plné etymologické odstavce. Některé soubory jsou výslovně CC BY-SA 4.0; [download](https://www.behindthename.com/api/download.php) má CAPTCHA. Pro cron zatím neimplementováno; vhodný ručně získaný konkrétní licencovaný export. [Jiné licencování](https://www.behindthename.com/info/licensing) má odlišná omezení včetně veřejného zobrazování, neslučovat režimy. |
| [MV ČR / požadavek v NKOD](https://data.gov.cz/návrh-na-datovou-sadu-k-otevření?iri=https%3A%2F%2Fdata.gov.cz%2Fzdroj%2Fpodněty-na-data-k-otevření%2FR23) | Současná četnost příjmení v ČR. | NKOD uvádí „Nelze publikovat“. Aktuální celostátní otevřenou sadu jsme neověřili. Starší kopie MV nelze prezentovat jako aktuální; případný historický import vyžaduje ověření původu a podmínek konkrétní kopie. |
| [Ridni – ukrajinská příjmení](https://ridni.org/karta/) | Mapy a informace o příjmeních. | Veřejný web existuje; otevřenou licenci a podporované exportní API jsem neověřil. Automatický scraper nepřidávám. Nezaměňovat stejnojmenné weby jiných organizací při hledání podmínek. |
| [DMS Ukrajiny – transliterace](https://dmsu.gov.ua/services/transliteration.html) | Oficiální přepis jmen do latinky. | Pravidla přepisu, nikoli etymologický slovník ani četnost. Případný budoucí lokální převodník je oddělená funkce, nepoužívat jako důkaz příbuznosti jmen. |

## Připravené úlohy a provoz

Nové seed SQL vytvoří dvě zapnuté české úlohy Wiktionary; pro sk/pl/uk/de vytvoří
po dvou připravených **vypnutých** úlohách podle pořadí rozšiřování projektu.
Polské národní statistiky jsou dvě zapnuté úlohy (muži/ženy), ČSÚ jedna zapnutá
úloha. Zapnutí úlohy znamená způsobilost pro CLI; seed neinstaluje systémový cron.

Všechny importy používají stávající `HttpClient`, tenantový zámek, transakci,
audit běhu, čas dalšího spuštění a bezpečné chybové kódy. Uložený obsah zůstává
redakčně upravitelný; opakovaný import aktualizuje pouze externí snapshot.
Smazané záznamy se neobnovují. Stavy chyb neznamenají, že dataset je prázdný.

Při živém průzkumu odpovídaly české, slovenské a polské kategorie Wiktionary.
Ukrajinský a německý pokus obdržel HTTP 429; jazyková podpora je připravená,
ale jejich živé zpracování tím nebylo potvrzeno. Respektujeme backoff a nepouštíme
současně více pokusů proti stejnému zdroji. Testy používají deterministické
fixtures, nikoli zátěž veřejných API.

Při publikaci je potřeba brát licenci a atribuci z konkrétního zdroje/citace,
nikoli z globálního nastavení aplikace. Zdrojový výklad, výskyt a literární
pověst zůstávají odlišné typy informací. Žádný z těchto importů automaticky
nedokazuje genealogické příbuzenství ani úřední chybu.


## Ověření implementace 28. 9. 2026

- Integrační test Etymologu: **232 kontrol**, izolovaný MySQL a skutečné HTTP
  endpointy včetně stávajícího přihlášení. Zahrnuje tenantové vazby, práva,
  opakování migrace/importu, licenci, změnu souboru během průchodu, ochranu
  ručních úprav a rollback celé dávky při SQL chybě.
- Regresní testy Transport: **58 kontrol**. HTTP sada prošla při samostatném
  opakování; první běh selhal na časově citlivé kontrole společného deadline.
  Kvůli tomu se neměnil HTTP modul. PHP lint prošel pro všech 27 souborů modulu,
  jeho API a CLI; `git diff --check` bez chyb.
- Po záloze byly do konfigurované aplikační DB aplikovány obě nové migrace.
  Porovnání se zálohou potvrdilo všech **118 původních řádků beze změny**.
- Úvodní živé dávky skutečně uložily **1000 výskytů PESEL** (500 mužských,
  500 ženských), **201 výskytů ČSÚ** a **1 etymologický koncept Wiktionary**.
  České křestní úlohy prohlédly první dvě hesla bez vhodného etymologického
  oddílu; kurzor pokračuje a nic se nedoplňuje odhadem.
- Toto je počáteční import, nikoli úplné naplnění zahraničních databází.
  Systémový cron nebyl instalován; příkaz a příklad cronu jsou v README modulu.

## Rozšíření: kulturní texty, pranostiky a jmeniny

Závazné pravidlo projektu: **AI nesmí příběhy, mýty, tradice ani pranostiky
vymýšlet.** Fikce je pouze převzaté existující literární dílo. Automatické
konektory přebírají text bez převyprávění. Ruční vložení vyžaduje webovou URL;
před publikací také licenci, atribuci a doslovný citát odpovídající tělu textu.
Redaktor ověřuje pravost ručně vložené citace. Kontrola textové shody sama
neprokazuje pravdivost ručně zadaného pramene ani historického děje.

### Erbenovy pranostiky a tradice — implementováno

[Prostonárodní české písně a říkadla](https://cs.wikisource.org/wiki/Proston%C3%A1rodn%C3%AD_%C4%8Desk%C3%A9_p%C3%ADsn%C4%9B_a_%C5%99%C3%ADkadla),
Karel Jaromír Erben, Praha: Jaroslav Pospíšil, **1864**. U každé importované
kapitoly API skutečně uvádí autora, bibliografii a **PD old 70**.

| Kapitola | Obsah | Vazba |
|---|---|---|
| [25. ledna](https://cs.wikisource.org/wiki/Proston%C3%A1rodn%C3%AD_%C4%8Desk%C3%A9_p%C3%ADsn%C4%9B_a_%C5%99%C3%ADkadla/25._ledna) | Dvě pranostiky, včetně Obrácení sv. Pavla | Pavel; datum historické kapitoly 25. 1. |
| [24. února](https://cs.wikisource.org/wiki/Proston%C3%A1rodn%C3%AD_%C4%8Desk%C3%A9_p%C3%ADsn%C4%9B_a_%C5%99%C3%ADkadla/24._%C3%BAnora) | Matějské pranostiky a zvyk se stromy; zachované regionální poznámky | Matěj, Josef; datum kapitoly 24. 2. |
| [12. března](https://cs.wikisource.org/wiki/Proston%C3%A1rodn%C3%AD_%C4%8Desk%C3%A9_p%C3%ADsn%C4%9B_a_%C5%99%C3%ADkadla/12._b%C5%99ezna) | Dětské výroční říkání a pranostika spojená s Řehořem | Řehoř; datum kapitoly 12. 3. |
| [Na jmena](https://cs.wikisource.org/wiki/Proston%C3%A1rodn%C3%AD_%C4%8Desk%C3%A9_p%C3%ADsn%C4%9B_a_%C5%99%C3%ADkadla/Na_jmena) | Tradiční dětská říkadla o jménech | Návrhy Mikuláš, Michal, Havel a příjmení Kučera; bez vymyšleného data |

Provider `erben-folklore` přebírá celé krátké kapitoly do `proverb` / `tradition`,
zachovává dobové znění a verše. Kniha může na jedné stránce spojovat pranostiky
se zvykem; nevyrábíme z textu nový souhrn. Jazykové přiřazení jmen je ručně
kurátorovaný návrh s `reviewed=0`, nikoli AI etymologie či genealogický důkaz.
Historický kalendář není automaticky dnešní církevní či občanský kalendář.

Při průzkumu se ukázalo, že některé položky obsahu knihy jsou nezpracované:
např. odkaz **24. dubna** je redlink a **Sv. Jan Křtitel** nemá hotovou kapitolu.
Import je nezahrnuje a chybějící text nenahrazuje novým vyprávěním.

### Český jmenný kalendář — implementováno

Repozitář [segeda/svatky-api-nodejs](https://github.com/segeda/svatky-api-nodejs),
[licence Unlicense](https://github.com/segeda/svatky-api-nodejs/blob/07f60431bf637238b09b6c27553b4f40494c7970/LICENSE.md).
Ověřený [soubor cs.js](https://github.com/segeda/svatky-api-nodejs/blob/07f60431bf637238b09b6c27553b4f40494c7970/cs.js)
obsahuje všech 366 kalendářních dat, **369 záznamů jmenin a 14 významných dnů**.
Jde o komunitní seznam, nikoli úředně závazný úplný seznam či odborný církevní
kalendář. Aktuální větev repozitáře nezaručuje, že vydavatelé dnešních kalendářů
uvádějí stejnou sadu jmen. Více jmen v jednom dni i více dat jména zachováváme.

`czech-namedays` zjistí commit přes GitHub API a data i licenci stáhne ze stejné
verze. Další dávka je připnutá ke stejnému commitu. Importuje jen JSON data z JS,
nikdy nespouští JavaScript. Známé svátky jako Nový rok, Tři králové a Štědrý den
jsou `observance`, nikoli jména. Licence má kontrolovaný otisk; změna vyžaduje
nové ověření. Zmínka Cyrila a Metoděje v názvu významného dne sama nevytvoří
dvě jmeniny. Soubory jiných zemí nejsou tímto providerem automaticky zahrnuté.

### Další vhodné weby k redakčnímu průzkumu

- [Wikicitáty — Pranostiky](https://cs.wikiquote.org/wiki/Pranostiky): rozcestník
  podle měsíců. Pro další import je nutné zachovat licenci konkrétního obsahu,
  citace a odlišit tradiční text od moderního autorského výroku. Zatím nepřidán.
- [NÚLK — elektronická knihovna](https://eknihovna.nulk.cz/kniha.php?k=62&s=str0084&typ=ocr):
  skeny a OCR národopisných pramenů, vhodné pro ověřování dobových tradic.
  Automatický import dalšího díla vyžaduje kontrolu edice a podmínek reprodukce.
- [Domažlický dějepis — Svatý Jiří](https://www.domazlicky-dejepis.cz/clanky/narodopis/lidove-zvyky/svatky-svatych/svaty-jiri.html):
  regionální obyčeje s uvedenou literaturou; článek uvádí autorská práva.
  Bez dohody nepřebíráme celý text. Je vhodný jako odkaz při redakčním průzkumu.

Původní Jiráskovy pověsti nadále poskytuje `wikisource`; tento krok přidává
pranostiky, doloženou tradici a kalendář. Pro další mytologická a literární díla
se katalog rozšiřuje až po ověření konkrétního dostupného textu a licence.


### Ověřený stav kulturního rozšíření

Po záloze a aplikaci migrace jsou v konfigurované DB skutečně uložené čtyři
nové Erbenovy kapitoly, tři historické kalendářní vazby a 383 záznamů českého
komunitního kalendáře (369 jmenin, 14 ostatních dnů). Obě nové úlohy dokončily
první průchod. Vše je koncept, vazby kulturních textů na jména čekají na kontrolu.
U čtyř dosavadních Jiráskových pověstí migrace doplnila původní citát ze snapshotu.
Porovnání 3460 původních řádků se zálohou potvrdilo zachování redakčního obsahu;
očekávanou změnou jsou pouze tyto zdrojové citace, webová URL a časy jejich změny.

Finální izolovaný integrační test: **300 kontrol**. Prošly také povinné HTTP
regrese, **58 kontrol Transportu**, PHP lint a `git diff --check`. Testy používají
syntetické odpovědi; oba nové konektory byly zvlášť ověřené proti živým zdrojům.
Systémový cron zůstává připravený v dokumentaci, nikoli nově instalovaný.
