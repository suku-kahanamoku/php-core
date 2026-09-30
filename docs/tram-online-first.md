# TRAM: online-first pravidlo pro dopravní data

Tento dokument popisuje požadované chování. Neznamená, že je už celé
implementované. Aktuální stav a mezery jsou uvedeny níže.

## Pravidlo zdrojů

1. Pro spojení, úseky, zastávky, jízdní řády, detaily a živé údaje se TRAM
   nejdřív ptá dostupných online API, která pokrývají danou oblast a operaci.
   Jednotlivé odpovědi normalizuje a skládá do jednotného výsledku.
2. Pokud API samo neumí najít celou cestu, PHP sestaví cestu z **online
   získaných** odjezdů, posloupností zastávek, časů jízd, přestupů a případných
   poloh. Po dobu jednoho dotazu vytvoří omezený graf v paměti. Nesmí si tiše
   pomoci jízdním řádem z naší databáze. Sestavení se provede jen tam, kde
   poskytovatelé skutečně nabízejí dost údajů pro ověření trasy a času.
3. Až při selhání relevantní online služby se použije její poslední platný
   importovaný snapshot. Výsledek jasně označí, která část je záložní,
   čas snapshotu a která živá pole nejsou dostupná. Úspěšná prázdná odpověď
   není výpadek. U neznámé nebo nepokryté trasy se nesmí vyrábět spojení.
4. Pozadový synchronizační proces udržuje katalog dat třetích stran pro
   výpadky. Například ve 02:00 zkontroluje změny jízdních řádů, validuje
   nový snapshot a teprve potom jej atomicky aktivuje. Četnost je
   konfigurovatelná podle zdroje; jeden noční běh nemusí stačit tam, kde se
   jízdní řád mění častěji. Synchronizace **nikdy nestahuje ani neukládá
   GPS polohy vozidel nebo polohy uživatelů**. Bez čerstvé online polohy
   vozidla je poloha nedostupná; žádná „poslední známá poloha“ se nečte z DB.

Databáze php-core může dál držet konfiguraci tenantů/providerů, stav výpadků
nebo metadata synchronizace. Požadavek „DB jen při výpadku“ se vztahuje na
**čtení importovaných dopravních dat při obsluze dotazu**. Výsledky živých
dotazů se nesmí v MySQL vydávat za aktuální data; detail se čte z online API
nebo ze záložního katalogu až při výpadku.

### Povolený obsah záložního katalogu

- Zastávky a stanice: identita, název, souřadnice **pevného místa**, nástupiště,
  bezbariérovost a další dostupné atributy. Přemístění zastávky při výluce
  znamená aktualizaci jejího záznamu v nové platné verzi, podle změn zdroje.
- Dopravci, linky, druhy dopravy, plánované spoje, posloupnosti zastávek,
  časy jízd, provozní kalendáře a výjimky, přestupní pravidla, tarify jen pokud
  existuje použitelný zdroj a oprávnění k uložení.
- Geometrie **plánované trasy** (například GTFS `shapes.txt`) je statický
  popis vedení linky. Není to aktuální poloha jedoucího vozidla.
- Metadata feedu: zdroj, licence, čas stažení, rozsah platnosti, kontrolní
  součet a stav synchronizace. Import ukládá vybraná pole podle schématu,
  nikoli celé odpovědi realtime API.

**Zakázáno ukládat:** GPS polohy vozidel a historii jejich pohybu, polohu
uživatele, souřadnice z jeho hledání a surové realtime odpovědi. Živá poloha
může existovat pouze v paměti po dobu vyřízení požadavku a odejít v odpovědi.
Při výpadku jejího API ji TRAM označí jako nedostupnou. Noční synchronizace
nemá co „zálohovat“ pro polohu jedoucího spoje.

## Rozdělení schopností

| Schopnost | Online zdroj / skládání | Záloha při výpadku |
| --- | --- | --- |
| Hledání celé cesty | API plánovače nebo PHP nad online úseky | Aktivní verzovaný jízdní řád a lokální plánovač |
| Hledání zastávek a detail | Online katalogy / geokodéry | Importovaný katalog zastávek |
| Odjezdy a detail spoje | Online API pro provozní den | Plánované časy ze snapshotu |
| Poloha vozidla | Čerstvé realtime API | Žádná uložená poloha; při výpadku je nedostupná |
| Zpoždění a výluky | Aktuální API | Jen plánované změny, pokud jsou součástí synchronizovaného jízdního řádu; jinak nedostupné |

Každý adaptér deklaruje své skutečné schopnosti. Golemio dnes v TRAM dodává
zastávky, odjezdy a polohy PID; jeho adaptér není plánovač celé cesty.
Online PHP skládání proto potřebuje také ověřený zdroj posloupnosti zastávek,
časů a provozních dnů konkrétních jízd. Propojení více dopravců vyžaduje
normalizaci ID, souřadnic, časových pásem, přestupních časů a spárování
spojů na tentýž provozní den. Nelze spojovat úseky pouze podle podobného
názvu zastávky. Algoritmus musí mít hranice počtu požadavků, času, počtu
přestupů a velikosti výsledku; jinak by mohl překročit limity cizích API.

## IDOS a česká data

IDOS je online vyhledávač s vlastním vyhledávacím enginem nad jízdními řády,
ne pouze proxy požadavků na API každého dopravce. Podle jeho nápovědy používá
CIS JŘ a další jízdní řády zpracované CHAPS. Realtime informace o některých
spojích získává zvlášť. Ministerstvo dopravy publikuje strojově čitelná data
CIS JŘ, vhodná pro rozšíření našeho záložního katalogu. PID publikuje GTFS
a samostatně online informace o polohách, zpoždění a odjezdech.

Veřejný dokument nazvaný „IDOS API“ popisuje odkazy na web IDOS s předvyplněným
vyhledáváním. Neobsahuje strojově čitelné výsledky pro naši aplikaci a
zakazuje zobrazovat výsledky jako součást cizího webu. Pro přímou integraci
výsledků IDOS do TRAM potřebujeme doložený výsledkový kontrakt a právo jeho
použití. IDOS může být kandidátem na online plánovač, ale nemůžeme jej
předstírat ani stahovat HTML výsledky jako API.

- [CHAPS: popis architektury IDOS](https://www.chaps.cz/cs/products/IDOS-internet)
- [IDOS: nápověda a zdroje dat](https://idos.cz/napoveda?hp=introduct&l=C)
- [CHAPS: veřejné API pro odkazy](https://www.chaps.cz/files/idos/IDOS-API.pdf)
- [Ministerstvo dopravy: CIS JŘ](https://md.gov.cz/Dokumenty/Verejna-doprava/Jizdni-rady,-kalendare-pro-jizdni-rady,-metodi-(1)/Jizdni-rady-verejne-dopravy)
- [PID: otevřená data](https://pid.cz/o-systemu/opendata/)

## Stav aktuálního kódu

- `pid-otp` je v `config/transport.example.json` stále primární plánovač.
  Praha proto běžně hledá nad naším importem. To odporuje tomuto návrhu.
- `ResourceService::places()` přimíchává importované zastávky i po úspěchu
  online API. `resource()` čte importovanou zastávku/spoj před online pokusem.
- Golemio se nevolá z `JourneyService::search()` a jeho realtime údaje se
  nespojují do výsledku hledání. Existuje jen samostatný dotaz na polohu
  svázaný s OTP ID.
- `TransportRepository::cacheJourney()` ukládá na 15 minut celý výsledek
  do MySQL pro následný detail/geometrii. Ten může zahrnovat souřadnice
  počátku/cíle uživatelského hledání nebo geometrii pěšího úseku. To je
  další nesoulad: cache je nutné odstranit či nahradit řešením, které
  souřadnice uživatele neukládá; detail se má číst online.
- Noční synchronizace všech poskytovatelů není naplánována. Hotový je
  verzovaný import GTFS pro PID, ale ne importy všech budoucích online zdrojů.

Aby mohl být PID režim označen jako hotový, je třeba doplnit online plánovač
nebo PHP skládání z dostatečně úplných online PID API, napojit Golemio na
výsledek, přesunout OTP do záložní role a opravit čtení lokálního katalogu.
Ostatní země/dopravci potřebují totéž pravidlo s vlastními adaptéry a
importéry. Pokrytí se nesmí odvozovat pouze z existence záložního feedu.
