# OpenAI modul

Serverova brana pro hlasovou konverzaci Rokid aplikace. Modul nedrzi vlastni
WebSocket proces, protoze produkcni PHP bezi pod CGI/FastCGI. Bezpecne vytvari
kratkodoby OpenAI Realtime client secret a skutecny WebSocket pote navazuje
mobilni aplikace primo s OpenAI.

## Endpoint

```http
POST /api/openai/realtime-session
X-Rokid-Key: <ROKID_AI_CLIENT_KEY>
```

Uspech vraci standardni envelope a v `data`:

```json
{
  "client_secret": "ek_...",
  "expires_at": 1756310470,
  "model": "gpt-realtime",
  "input_audio_format": {"type": "audio/pcm", "rate": 24000},
  "output_modalities": ["text"]
}
```

Token plati 60 sekund pro vytvoreni relace. Relace pouziva server VAD s delsim
700ms tichem pro stabilnejsi deleni souvisleho dialogu. VAD má vypnuté
automatické `create_response`; Android po další 1,5sekundové tiché prodlevě
řízeně vyžádá jedinou analýzu nad nahromaděným kontextem. Nová řeč nepřeruší
právě generované argumenty function callu. Relace používá textový výstup a limit
1024 output tokenů pro bezpečné dokončení argumentů s úplným potvrzeným záměrem.
Povinné volání nástroje dovoluje pouze `recommend_product`, `get_product` a lokální
`continue_listening`; model proto nemůže místo výběru produktu vrátit volný text.
`continue_listening` vždy nese aktuálně potvrzenou kategorii a cenový záměr;
prázdný string označuje neznámou hodnotu a Android z něj aktualizuje checklist.
`continue_listening` navíc vrací `active_need` s potvrzenými preferencemi,
omezeními, použitím a příjemcem i před prvním doporučením. Oba analytické
nástroje nesou `change_intent` (`maintain`, `update`, `replace`, `return`), aby
Android zachoval požadavek na náhradu i přes výpadek. Jde o faktický nákupní
stav, nikoli přepis konverzace.
Katalogové nástroje mobil vykoná přes následující backendový endpoint.

```http
POST /api/openai/tool
X-Rokid-Key: <ROKID_AI_CLIENT_KEY>
Content-Type: application/json

{"name":"recommend_product","arguments":{"query":"jiná svěží parfémová voda na každý den do 2500 Kč","category":"parfém","price_intent":"maximálně 2500 Kč","excluded_product_id":85}}
```

Katalogový endpoint je tenantově omezený, rate-limitovaný na 60 volání za
minutu a nezpřístupňuje obecné admin API. PHP neposuzuje rozhovor, nefiltruje
produkty podle požadavků a nevytváří vlastní pořadí kandidátů.

`recommend_product` vyžaduje český `query`, konkrétní `category` a potvrzený
`price_intent`; při bezprostřední náhradě může Android přidat aktuální
`excluded_product_id`. Volitelné `current_product_id` modelu identifikuje
aktuální kartu i tehdy, když Realtime neoznačí náhradu booleanem. `query`
může mít až 3000 znaků. PHP hodnoty pouze validuje a předá OpenAI Responses modelu. Ten
pomocí hostovaného `file_search` vyhledá dokumenty v tenantovém Vector Store,
sám porovná názvy, popisy, kategorie, varianty, cenu, dostupnost a
`selection_attributes` a vrátí konkrétní `product_id`, kvalitu `exact` nebo
`nearest` a krátký český důvod nejbližší alternativy. PHP
nepřijímá ani nevrací pořadí kandidátů.
`file_search` má limit 50 výsledků na hledání. Před nejbližší alternativou má
model provést další cílené hledání, celkem nejvýše tři přes `max_tool_calls`, a
porovnat evidenci napříč výsledky. Nejde o kompletní průchod katalogu.
Výstup zůstává striktní JSON s limitem 512 tokenů; model nesmí z pouhé
chybějící první shody tvrdit, že produkt neexistuje v celém katalogu.

`get_product` přijímá pouze `product_id`, které zvolil Responses model z
`file_search` evidence. PHP vrátí aktuální publikovaný katalogový záznam, ale
neoznačuje jej za doporučený ani ověřený vůči rozhovoru. Pokud Vector Store
není připravený nebo OpenAI Retrieval selže, vrací se stav `unavailable` bez
databázového fallbacku; PHP tedy nikdy samo nevybere náhradní produkt.

OpenAI Vector Store je vyhledávací znalostní index, nikoli zdroj pravdy ani
fine-tuning modelu. Každý publikovaný produkt se ukládá do samostatného JSON
souboru s ID, názvem, popisem, kategoriemi, variantou, přesnou cenou s DPH,
měnou, dostupností a
výběrovými atributy. Databáze zůstává katalogovým zdrojem a synchronizace
udržuje index aktuální pomocí SHA-256 otisku finálního dokumentu.

Realtime API nemá přímý hostovaný nástroj `file_search` z Responses API.
Realtime model proto volá úzký function nástroj `recommend_product`. PHP v něm
nečte produktový katalog ani cenu a neobsahuje doporučovací algoritmus; pouze
drží serverový API klíč a pošle požadavek do Responses API. OpenAI Responses
provede `file_search`, rozhodne a vrátí jediné doložené ID. Po dokončení povinné
brány nikdy nevrací obchodní `no_match`: pokud přesná skladová, cenová nebo
atributová shoda neexistuje, vybere nejbližší produkt a popíše odchylku. Teprve následné
`get_product` načte právě jeden publikovaný produkt z databáze.

Realtime relace analyzuje celý rozhovor a neposílá volný text určený k
zobrazení. Rozlišuje osobu a nákupní záměr, potvrzená fakta, jednoznačně
vyjádřený význam, hypotézy a neznámé údaje. Nikdy nepokládá otázku a negeneruje
prodejní argument. Primární hledání začne až tehdy, když je známá
konkrétní kategorie produktu a současně cenový záměr nebo důvěryhodný
normalizovaný profil. Obecné „produkt“, „kosmetika“ ani účel „dárek“ nejsou
kategorií. Profilový kontext zatím Realtime relaci není zpřístupněný, takže v
aktuálním kontraktu musí být potvrzená kategorie i cena. Do té doby relace volá
`continue_listening` se stavem obou bodů pro checklist obchodníka. Responses model z `file_search` dokumentů vybere vždy jeden produkt; bez přesné shody vrátí nejbližší doloženou alternativu a důvod pro displej. PHP
pro něj pouze načte aktuální katalogový detail. Nespokojenost nebo žádost o jiný,
další či lepší produkt nastaví `replace_current_product`; Android z něj odvodí
aktuální `excluded_product_id` a Responses `file_search` jej přes metadatový filtr
`product_id != ID` pro tento jediný výběr povinně vyřadí. Žádné dříve zobrazené ID se trvale nevyloučí. Zákazník se k němu může později
vrátit. „Lepší“ znamená přesnější shodu s doloženými požadavky, nikoli vyšší
cenu, popularitu nebo marži.

Odmítnutí nemusí být příkaz „další produkt“: jasné „je moc sladký, raději
svěžejší“ nebo „ten krém je příliš hutný, chci lehčí“ má vyvolat nový výběr,
jakmile zůstává splněná povinná brána. Realtime zachová kategorii, rozpočet
a ostatní platné požadavky a předá důvod nespokojenosti i nově požadované
atributy Responses modelu. Ten je použije při `file_search` i porovnání
kandidátů; nestačí jen jiné ID se stejnou nežádoucí vlastností. Bez uvedeného
důvodu se hledá jiná vhodná alternativa bez domýšlení parametrů. Nové parametry
zůstávají součástí potřeby až do jejich opravy zákazníkem; vyloučení ID je
naproti tomu jednorázové. Samotné mlčení, tón hlasu, hypotetická poznámka nebo
návrh obchodníka nejsou potvrzené odmítnutí. Pokud neexistuje přesná doložená
alternativa, pravidla vyžadují nejbližší jiný dostupný kandidát s důvodem
odchylky; technický výpadek nebo chybějící jiný kandidát nelze vyřešit
vymyšleným produktem.

Změna rozpočtu nebo kategorie je nový signál i bez odmítnutí aktuální karty.
Realtime musí přepsat `category`, `price_intent` i úplný nákupní záměr a
předat `change_intent=update` (při současném odmítnutí `replace`). Například
„původně do 1000, teď do 2000 Kč“ nahrazuje původní limit; „cena nerozhoduje“
jej výslovně ruší. „Podobný, ale levnější“ zachová přijaté vlastnosti a doplní
relativní cenu vůči produktu s doloženou cenou, měnou a variantou z katalogového
výsledku, pokud jsou dostupné. Nevymýšlí částku; „dražší“ samo neruší potvrzený
strop. Nedoložené relativní cenové porovnání Responses nesmí označit jako `exact`.
Přechod z parfému na krém odstraní například vonné tóny a projekci, ale zachová
požadavky, jejichž platnost pro novou kategorii vyplývá z rozhovoru. Samostatný
nákup nebo nový příjemce nesmí zdědit nesouvisející požadavky. Odvolaný bod bez
náhrady se v checklistu vyprázdní a relace opět čeká na splnění povinné brány.
Pokud brána zůstává úplná, následuje nové hledání s aktuálními hodnotami a
jednorázovou náhradou zobrazené karty; starý výsledek se nesmí načíst. Případný
návrat ke staršímu produktu musí rovněž respektovat nové platné požadavky.

### Doplněk po rozhodnutí o nákupu

Po jasném zákaznickém „tenhle si vezmu“, vztahujícím se ke známému ID,
instrukce dovolují jednu volitelnou doplňkovou kartu. Jde o cross-sell
(produkt navíc), nikoli dražší náhradu původního produktu. Pochvala,
neurčité „možná“ ani návrh obchodníka nestačí; nejde o důkaz objednávky či
platby. Hlavní ID, relevantní atributy, potvrzení a stav nabídky zůstávají
v `active_need`/`query`. Odmítnutí doplňku neodmítne hlavní produkt.

`recommend_product` přijímá volitelné kladné `addon_for_product_id`.
Android ověří, že jde o známou zobrazenou kartu. Pouze při jeho uvedení smí
`price_intent` zůstat prázdný: znamená neznámý doplňkový rozpočet, nikoli
neomezenou ochotu utrácet. Primární rozpočet se nepřenáší a jeho běžná brána
se nemění. Případný dodatečný či celkový strop musí model respektovat a
zbývající částku smí odvodit jen z doložených cen. Kategorie doplňku může být
funkčně odvozená, ale v zákaznickém checklistu zůstávají hlavní potvrzené body.

Responses vyhledá a vyhodnotí jeden vhodný skladový doplněk podle atributů
ve Vector Store, například odstranění líčení vhodným odličovačem. Žádná
pevná značka, SKU vazba ani nový scraper nejsou součástí tohoto kroku.
PHP nepřidává rozhodovací skóre ani nečte katalog před výběrem. Metadatový
filtr povinně vyřadí hlavní ID a případně odmítnutou aktuální doplňkovou
kartu. Po výběru se přes běžný `get_product` načte přesný detail; Android
přidá označení „Doplněk k: …“.

Pouze doplňkový režim dovoluje `no_match` s `product_id=null`, pokud chybí
doložený užitečný kompatibilní skladový produkt nebo potvrzení nákupu.
Nenutí nesouvisející nejbližší produkt a současná karta zůstává viditelná.
Primární režim stále vyžaduje `selected`. Pravidla neřetězí automatické nabídky,
neopakují je při dalším potvrzení téhož nákupu, respektují vlastněný či
nechtěný doplněk a „nic dalšího nechci“. Změna nebo odvolání hlavního
rozhodnutí vrací model do primárního výběru. Jde o modelově řízené přechody,
které vyžadují živé ověření; offline testy ověřují kontrakt a obranné kontroly.

`migrations/fann_seed.sql` obsahuje sloučený FAnn katalog se zdrojovou URL,
datem kontroly, variantami a výběrovými atributy. Doplňuje pouze chybějící řádky.
Pořadí instalace popisuje `migrations/README.md`.

## Konfigurace

```dotenv
OPENAI_API_KEY=sk-proj-...
ROKID_AI_CLIENT_KEY=<nahodny retezec alespon 32 bytu>
OPENAI_RECOMMENDATION_MODEL=gpt-5.6-terra
OPENAI_VECTOR_STORE_ENABLED=false
```

`OPENAI_API_KEY` ani `ROKID_AI_CLIENT_KEY` nepatri do Gitu. Tenant se vybere
standardnim mechanismem `php-core` podle hostu pozadavku. Vytvoreni relace je
omezeno na 10 a katalogove nastroje na 60 pokusu za minutu a vzdalenou adresu.

`ROKID_AI_CLIENT_KEY` chrani soukromy prototyp, ale staticky klic vlozeny do APK
lze ziskat reverzni analyzou. Pred verejnou distribuci jej nahraď prihlasenim
uzivatele nebo atestaci zarizeni; hlavni OpenAI klic zustava vzdy jen na serveru.

## Odpovednosti

- `InternalAuthMiddleware` ověřuje `X-Rokid-Key` před vytvořením API a DB; `OpenAiApi` pak kontroluje rate limit a používá vyřešeného tenanta.
- `OpenAiRealtimeService` vola `POST /v1/realtime/client_secrets`.
- `OpenAiCatalogGateway` vystavuje jen produktove operace potrebne pro synchronizaci
  a nacteni jednoho vysledku; OpenAI modul neni zavisly na profilech zakazniku.
- `OpenAiCatalogRepository` nacita publikovana tenantova data pres existujici moduly.
- `OpenAiProductDocumentBuilder` vytváří stabilní produktové JSON dokumenty.
- `OpenAiVectorStoreSyncService` inkrementálně nahrává změněné produkty a odstraňuje
  indexy produktů, které už nejsou publikované.
- `OpenAiResponsesProductRecommender` volá OpenAI Responses s hostovaným
  `file_search`, striktním JSON výstupem a kontrolou ID proti vyhledané evidenci.
- `OpenAiVectorStoreRepository` drží tenantové ID indexu a vazbu produktu na
  OpenAI soubor; tajný OpenAI klíč ani obsah rozhovoru neukládá.

Realtime model lze bez změny kódu nastavit přes `OPENAI_REALTIME_MODEL`; výchozí
hodnota je `gpt-realtime`. Doporučovací Responses model nastavuje
`OPENAI_RECOMMENDATION_MODEL`; výchozí je `gpt-5.6-terra`. Systémové instrukce a popisy nástrojů jsou stručně
strukturované v angličtině, analyzovaný rozhovor však zůstává český. Jazyk instrukcí sám o sobě negarantuje
nižší cenu ani vyšší přesnost; rozhodující je jejich jednoznačnost, délka a
ověření na reálných dialozích. Prompt je tenantově neutrální a konkrétní profily
i produkty vždy pocházejí z hostem vybraného katalogu.
- `OpenAiKnowledgeCatalogService` validuje úzký recommendation/detail kontrakt, ale
  nerozhoduje o vhodnosti produktu.
- Chyby upstreamu se mapuji na obecny stav 502 a nikdy nevraceji telo OpenAI
  odpovedi ani serverovy API klic.

## Testovani

Offline unit test nepouziva skutecny OpenAI ucet:

```bash
php8.2 src/Modules/OpenAi/tests/OpenAiRealtimeServiceTest.php
php8.2 src/Modules/OpenAi/tests/OpenAiKnowledgeCatalogServiceTest.php
php8.2 src/Modules/OpenAi/tests/OpenAiResponsesProductRecommenderTest.php
php8.2 src/Modules/OpenAi/tests/OpenAiVectorStoreTest.php
```

## První synchronizace a pravidelná aktualizace

Na existující databázi se nejprve spustí aditivní migrace:

```bash
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" \
  < migrations/schema.sql
```

Po nastavení serverového `OPENAI_API_KEY` vytvoří první příkaz tenantový Vector
Store a nahraje publikované produkty. Další běhy jsou inkrementální podle
SHA-256 otisku dokumentu:

```bash
php8.2 scripts/sync_openai_vector_store.php --tenant=fann
```

Teprve po úspěšné první synchronizaci se pro běžné API nastaví
`OPENAI_VECTOR_STORE_ENABLED=true`. Synchronizaci lze spouštět po katalogovém
importu nebo pravidelně z cronu, například jednou za hodinu. Souběžné běhy nad
stejným tenantem služba odmítne tenantovým databázovým zámkem. Při změně produktu vznikne nejprve nový
hotový index a až poté se odpojí starý soubor, takže běžné vyhledávání nepřijde
o poslední platnou verzi.

Zivy smoke test vyzaduje produkcni env hodnoty a platny tenant host. Hodnoty
tajnych hlavicek nevypisuj do logu ani je neukladej do shell historie.
