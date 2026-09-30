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

## Aktualizace modularity

Transport nyní používá integrační moduly a společný executor pro vícekrokové
souběžné dotazy, jeden rozpočet od rozlišení míst a kvótu každého externího requestu.
Golemio sdílí kvótu podle credential scope napříč tenanty. Země se skládají pomocí
konfiguračních presetů. Pokrytí cesty určují koncové body, stát/město UI ji
neomezují; geografický filtr našeptávače zůstává zachovaný. Detail návrhu a
přesné implementované hranice jsou v
[Transport/ARCHITECTURE.md](../src/Modules/Transport/ARCHITECTURE.md).
Níže uvedená historická měření nejsou novým produkčním ověřením.

## Stav aktuálního kódu (30. 9. 2026)

### Sdílený výběr oblasti a online našeptávač

`ProviderSelectionService` je společný výběr pro `ResourceService::places()` a
`JourneyService::search()`: tenant → schopnost → stát → město/pokrytí GPS.
Nevolá se jeden ručně zvolený dopravce; osloví se všichni vhodní poskytovatelé
v registru, kteří danou operaci skutečně podporují. Detaily zastávky/spoje se
naopak směrují podle ověřeného ID zdroje, aby se nezaměnily identity dopravců.

- `coverage[].country` používá ISO kód; volitelné `cities` vymezuje městské
  služby. Bez tohoto omezení zůstává zdroj použitelný pro města v jeho širším
  pokrytí. Městský dotaz tedy zachová také národní zdroj.
- Prázdné město znamená hledání napříč městy vybraného státu. Prázdný stát
  bez GPS znamená všechny nakonfigurované státy.
- Výslovně zadané město má přednost před GPS. Stát lze kombinovat s GPS pro
  lokální výběr; pokud je zařízení mimo zadaný stát, rozhoduje zadaný stát.
- GPS výběr používá nakonfigurované `bbox`. **Není to zatím globální reverse
  geocoder ani přesná administrativní hranice obcí/států.** U hranic se mohou
  oblasti překrývat; přesné rozlišení vyžaduje doplnit geografický resolver
  a kvalitnější pokrytí. Národní našeptávač dostane GPS jako polohovou nápovědu,
  nikoli jako prokázaný název obce.
- Plánovač navíc musí obsluhovat oba konce cesty, nikoli pouze polohu telefonu.
  Vlastní online ověřené ID zastávky může použít i tehdy, když zdroj neuvádí GPS.
- Čerstvá poloha jde pouze v POST těle, nepersistuje se. `city`, `country` a
  volba GPS se uchovávají v URL frontendu; měření se po obnově získá znovu.

Nový `POST /transport/v1/places/search` přijímá standardní `q`, `limit`,
`page`, `sort`, `projection`. Například
`{"q":{"name":{"$regex":"Vaclav"},"state":"CZ","city":"Brno"},"limit":20}`.
`$regex` zde představuje bezpečný textový podřetězec, nikoli spouštěný regulární
výraz. Pro GPS obsahuje `q` navíc `latitude`, `longitude`, `observed_at`;
fix musí být čerstvý do 30 sekund. Starý GET endpoint zůstává kompatibilní.

`SpojenkaProvider` doplňuje online české zastávky, detail zastávky, hledání cest
s přestupy a datovaný detail spoje. Používá veřejně popsané REST rozhraní přes
HttpModule; nečte náš jízdní řád pro úspěšný online požadavek. `SpojenkaMapper`
vrací pouze plánovaná pole, nepovažuje odkazy na realtime službu za aktuální
polohy. Nativní ID Spojenky se zatím nepárují s PID realtime; tento zdroj tedy
neposkytuje živou polohu ani garantované aktuální zpoždění.

Vzor `config/transport.cz-online.example.json` zapíná Spojenku a Golemio.
U Golemio vypíná pouze `places` (jeho `names[]` potřebuje přesný název a
neposkytuje použitelné celostátní podřetězcové našeptávání). Ostatní schopnosti
Golemio zůstávají dostupné. Český vzor byl aplikován lokálně, nikoli produkčně.
Konfigurátor neodstraňuje starší záznamy, proto je při nasazení nutné sloučit
konfiguraci s existujícími providery a vazbami na fallback.

**Provozní omezení:** oficiální OpenAPI označuje použitý endpoint jako
„MFF development server“. Dostupnost, kvóty a podmínky produkčního použití
nejsou touto implementací zaručené. Není připojen nový importér národních dat
ani záložní plánovač pro Spojenku: její výpadek bez odpovídajícího snapshotu
vrátí nedostupnost. PID snapshot nelze vydávat za zálohu celé ČR. Úplnost IDOS
ani veškeré zahraniční pokrytí tato změna neslibuje.

Zdroje: [Spojenka REST dokumentace](https://www.spojenka.cz/swagger/spojenka-rest),
[OpenAPI a popis serveru](https://www.spojenka.cz/openapi-schemas/spojenka-rest.json?v202608211834),
[zdroje jízdních řádů](https://www.spojenka.cz/jrdata).

Lokálně ověřeno: `Lazar` → Praha, Lazarská; `Grohova` → Brno, Grohova;
`Vaclav` → výsledky z více měst včetně Brna a Prahy; městský filtr;
vyhledání Grohova → Václavská včetně pěšího dokončení a detailu spoje.
118 kontrol v izolované MySQL a 13 browserových scénářů. Živý test není SLA.

### Ostatní implementované části a zbývající mezery

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
  jeden přestup samotný Golemio adaptér nepokrývá; místo skrytého čtení katalogu
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
  Výchozí limit Golemio je 20 požadavků za 8 sekund na klíč; sdílenou
  kvótu nyní hlídá transportní executor. Vyšší kapacita vyžaduje odpovídající limit zdroje.
- Import PID GTFS je verzovaný a ručně spustitelný. Plánování nočních úloh,
  importy dalších zdrojů a produkční ověření nejsou dokončené. Lokální
  Golemio token byl ověřen přímým online čtením zastávek. Neexistuje oprávněné výsledkové API IDOS
  zapojené do TRAM.

Další rozšíření plánovače musí zachovat online zdroj dat, přesný identifikátor
zastávky a provozní den i pevný rozpočet požadavků. Pro úplné celosvětové
vyhledávání je nutné přidávat skutečné online plánovače a importéry podle
licencí konkrétních provozovatelů.

### Aktuální poloha jako počátek/cíl cesty

`current_location` se před plánováním převádí přes `NearestStopService` na
konkrétní veřejnou zastávku. Společný výběr poskytovatelů použije pokrytí GPS
a schopnost `nearby_stops` (Spojenka, Entur s geocoderem). Z online kandidátů
vybere nejbližší podle geografické vzdálenosti, nejvýše 2 km; nepředstírá
výpočet pěší dostupnosti přes ulice či překážky. Ručně zadané `coordinates`
zůstávají bodovým plánováním. Nejbližší zastávka se neurčuje podle názvu obce.

Plánovač dostane ID vybrané zastávky. Čas odjezdu/příjezdu se vztahuje na ni;
příchod od GPS na zastávku není součástí výpočtu. Výsledek přidává
`resolved_places.from` / `resolved_places.to` s veřejnými poli zastávky,
které web zobrazí i u prázdného výsledku. Při chybějící zastávce dostane klient
`nearby_stop_not_found`, při nepokrytí `unsupported_coverage`, při výpadku bez
platné zálohy `sources_unavailable`.

Pouze po výpadku zdroje lze sáhnout do jeho platného importovaného katalogu.
Úspěšný prázdný výsledek ani běžné throttlování fallback neaktivují.
GPS fix se znovu ověří i po odpovědi; původní soukromý charakter dotazu se
zachová při sanitizaci cache. `resolved_places` ani měření polohy se neukládá.
Po refreshi se použije nový fix a provede nový výběr zastávky.

## Země a město ve formuláři TRAM

Frontend začíná jedinou záložkou Česká republika. Výběr města nad Odkud/Kam
má výchozí volbu Všechny jízdní řády. Nabízí Prahu a Brno a další obce dohledává
přes existující online `places` s `q.name.$regex` a `q.state`; nepřidává čtení DB
za zdravého provozu. Spojenka mapuje obec do `city` ze strukturovaného
`placeHierarchy` typu `MUNICIPALITY`, pole je povolené v projekci seznamu míst.

`JourneyAreaService` vrací pro výsledky společné město v `area.city`. Oba
vyřešené konce cesty musí mít shodnou obec a konce všech zobrazených úseků
musí odpovídat této obci (ověřená ID konců, metadata obce nebo kvalifikovaný
název zastávky). Mezíměstská cesta či neznámá obec znamená `null`. Kontrola
neověřuje přesnou geometrii ani skryté průjezdní zastávky. Platí také pro
aktuální GPS rozlišenou na zastávku; její poloha se nadále neukládá.

Frontend upraví město a URL bez opakování dotazu a bez dalšího požadavku na
GPS. Refresh obnoví oblast, detail a mapu. Prázdné či neúspěšné hledání
ponechá původní volbu města.
