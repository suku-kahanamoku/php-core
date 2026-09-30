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
Při výpadku jejího API ji TRAM označí jako nedostupnou. Stará souřadnice
vozidla se nevrací ani s příznakem `stale`; místo ní je `position: null`.
Noční synchronizace nemá co „zálohovat“ pro polohu jedoucího spoje.
GPS poloha uživatele se přijímá pouze jako `current_location` s časem měření
starým nejvýše 30 sekund. Ručně vybraný bod `coordinates` je cíl či počátek
plánování, nikoli údaj o pohybu uživatele. Backend uživatele nesleduje a
neukládá ani jedno hledání s jeho souřadnicemi do katalogu.

## Rozdělení schopností

| Schopnost | Online zdroj / skládání | Záloha při výpadku |
| --- | --- | --- |
| Hledání celé cesty | API plánovače nebo PHP nad online úseky | Aktivní verzovaný jízdní řád a lokální plánovač |
| Hledání zastávek a detail | Online katalogy / geokodéry | Importovaný katalog zastávek |
| Odjezdy a detail spoje | Online API pro provozní den | Plánované časy ze snapshotu |
| Poloha vozidla | Čerstvé realtime API | Žádná uložená poloha; při výpadku je nedostupná |
| Poloha uživatele | Čerstvý GPS fix klienta s časem měření | Žádná uložená poloha ani poslední známý bod |
| Zpoždění a výluky | Aktuální API | Jen plánované změny, pokud jsou součástí synchronizovaného jízdního řádu; jinak nedostupné |

Každý adaptér deklaruje své skutečné schopnosti. Golemio poskytuje zastávky,
časy, detaily spojů, odjezdy a polohy PID; samo nevrací hotové itineráře.
TRAM z jeho online GTFS stop times a posloupností zastávek nyní skládá omezené
cesty v PHP. Širší plánování potřebuje další online zdroje a algoritmy. Propojení více dopravců vyžaduje
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

## Stav aktuálního kódu (30. 9. 2026)

- Entur poskytuje online plánování pro své pokrytí. PID má jako primární zdroj
  Golemio: PHP z jeho online stop times a detailů jízd skládá přímé spojení
  a jeden přestup na **identické zastávce** s minimálně třemi minutami.
  Zahrnuje provozní den a časy přes půlnoc. Hledání je omezené na čtyři hodiny,
  nejvýše 16 stop times na zastávku/den a čtyři kandidátní jízdy z každého
  konce. Výsledek proto nese `partial: true`, `source.limited: true` a varování.
  Prázdný úspěšný výsledek z tohoto omezeného hledání není důkaz, že cesta
  neexistuje, a nespouští záložní plánovač.
- Příklad konfigurace nastavuje `pid-otp` jako `fallback_for: ["pid"]`.
  OTP nad importovaným jízdním řádem se použije při výpadku PID online služby,
  nikoli jako primární český plánovač. Hledání od souřadnic v PID a více než
  jeden přestup zatím online adaptér nepokrývá; místo skrytého čtení katalogu
  vrací nepodporovanou schopnost. Chybí i přestup mezi blízkými, ale odlišnými
  zastávkami, chůze a záruka úplnosti podobná IDOS.
- Našeptávač používá online místa při úspěchu zdroje. Importovaný katalog
  čte jen po selhání relevantního zdroje nebo při otevřeném circuit breakeru;
  běžné omezení frekvence volání není důvod k lokálnímu fallbacku. Detail
  zastávky i spoje se nejprve čte online. Staré ID z OTP se u podporovaných
  PID zdrojů mapuje zpět na online Golemio.
- Golemio doplňuje vyhledané úseky o online predikce odjezdu a odřeknutí
  pouze po shodě ID zastávky, ID jízdy a plánovaného času. Živá poloha se
  poskytuje jen online, po ověření konkrétního provozního dne; nikdy se
  nesynchronizuje ani neukládá do katalogu. Souřadnice uživatelského dotazu,
  pěší geometrie a telemetrie jsou odstraněné i z krátkodobého detailu.
- **Zbývající nesoulad:** `transport_journey_cache` stále uchovává na 15 minut
  omezený plánovaný výsledek a `GET /journeys/{id}` jej čte z MySQL, přestože
  požadovaný cílový stav používá DB jen jako záložní katalog. Cache už
  neukládá polohy uživatelů ani vozidel. Náhrada detailového kontraktu
  online/ephemerálním úložištěm vyžaduje samostatné řešení pro více PHP
  instancí; endpointy detailu a geometrie zůstaly funkční.
- Záložní výsledky uvádějí verzi snapshotu a UTC čas dokončeného importu.
  Výchozí limit Golemio je 20 požadavků za 8 sekund na klíč; při souběžném
  provozu je potřeba sdíleně hlídat kvótu nebo domluvit vyšší limit.
- Import PID GTFS je verzovaný a ručně spustitelný. Plánování nočních úloh,
  importy dalších zdrojů, ostrý Golemio token a integrační test proti jeho
  živému API nejsou dokončené. Neexistuje oprávněné výsledkové API IDOS
  zapojené do TRAM.

Další rozšíření plánovače musí zachovat online zdroj dat, přesný identifikátor
zastávky a provozní den i pevný rozpočet požadavků. Pro úplné celosvětové
vyhledávání je nutné přidávat skutečné online plánovače a importéry podle
licencí konkrétních provozovatelů.
