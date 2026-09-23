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
512 output tokenu pro bezpečné dokončení strukturovaných argumentů.
Povinné volání nástroje dovoluje pouze `retrieve_products`, `get_product` a lokální
`continue_listening`; model proto nemůže místo výběru produktu vrátit volný text.
Katalogové nástroje mobil vykoná přes následující backendový endpoint.

```http
POST /api/openai/tool
X-Rokid-Key: <ROKID_AI_CLIENT_KEY>
Content-Type: application/json

{"name":"retrieve_products","arguments":{"query":"svěží parfémová voda na každý den do 2500 Kč","limit":10}}
```

Katalogový endpoint je tenantově omezený, rate-limitovaný na 60 volání za
minutu a nezpřístupňuje obecné admin API. PHP neposuzuje rozhovor, nefiltruje
produkty podle požadavků a nevytváří vlastní pořadí kandidátů.

`retrieve_products` přijímá přirozený český `query` a `limit` 1 až 20. PHP
dotaz beze změny předá tenantovému OpenAI Vector Store a vrátí Realtime modelu
produktové dokumenty v pořadí Retrieval API včetně podobnostního skóre. Model
sám čte názvy, popisy, kategorie, varianty, cenu, dostupnost a
`selection_attributes`, porovnává je s celým rozhovorem a vybírá konkrétní ID.

`get_product` přijímá pouze `product_id`, které zvolil model z posledního
retrieval výsledku. PHP vrátí aktuální publikovaný katalogový záznam, ale
neoznačuje jej za doporučený ani ověřený vůči rozhovoru. Pokud Vector Store
není připravený nebo OpenAI Retrieval selže, vrací se stav `unavailable` bez
databázového fallbacku; PHP tedy nikdy samo nevybere náhradní produkt.

OpenAI Vector Store je vyhledávací znalostní index, nikoli zdroj pravdy ani
fine-tuning modelu. Každý publikovaný produkt se ukládá do samostatného JSON
souboru s ID, názvem, popisem, kategoriemi, variantou, cenou, dostupností a
výběrovými atributy. Databáze zůstává katalogovým zdrojem a synchronizace
udržuje index aktuální pomocí SHA-256 otisku finálního dokumentu.

Realtime API nemá přímý nástroj `file_search` z Responses API. Realtime model
proto volá úzký function nástroj `retrieve_products`. PHP v něm pouze technicky
provede Retrieval API request a vrátí dokumenty; samotné rozhodnutí zůstává
v Realtime modelu.

Realtime relace analyzuje celý rozhovor a neposílá volný text určený k
zobrazení. Rozlišuje osobu a nákupní záměr, potvrzená fakta, jednoznačně
vyjádřený význam, hypotézy a neznámé údaje. Nikdy nepokládá otázku a negeneruje
prodejní argument, upsell ani cross-sell. Jakmile zachytí libovolný použitelný
nákupní signál, hledá ihned; neznámé vlastnosti ponechá bez omezení. Jen čisté
pozadí, nedokončená řeč nebo nezměněný stav ukončí lokálním nástrojem
`continue_listening`. Model z retrieval dokumentů vybere jediný produkt a PHP
pro něj pouze načte aktuální katalogový detail. Nespokojenost nebo žádost o jiný,
další či lepší produkt odmítne současné ID, ale zachová stále platné požadavky
aktivní potřeby. „Lepší“ znamená přesnější shodu s doloženými požadavky, nikoli
vyšší cenu, popularitu nebo marži. Změna potřeby vždy spustí nové hledání. Zákaznické
profily nejsou Realtime relaci zpřístupněné.

Migrace `migrations/20260921_fun_product_catalog_enrichment.sql` idempotentně
obohacuje 23 dohledaných existujících FAnn produktů a přidává 30 aktuálních variant. Ukládá
zdrojovou URL, datum kontroly, značku, variantu, EAN, diagnostické otázky a
normalizované výběrové atributy v `data`; historický seed nemění.

## Konfigurace

```dotenv
OPENAI_API_KEY=sk-proj-...
ROKID_AI_CLIENT_KEY=<nahodny retezec alespon 32 bytu>
OPENAI_VECTOR_STORE_ENABLED=false
```

`OPENAI_API_KEY` ani `ROKID_AI_CLIENT_KEY` nepatri do Gitu. Tenant se vybere
standardnim mechanismem `php-core` podle hostu pozadavku. Vytvoreni relace je
omezeno na 10 a katalogove nastroje na 60 pokusu za minutu a vzdalenou adresu.

`ROKID_AI_CLIENT_KEY` chrani soukromy prototyp, ale staticky klic vlozeny do APK
lze ziskat reverzni analyzou. Pred verejnou distribuci jej nahraď prihlasenim
uzivatele nebo atestaci zarizeni; hlavni OpenAI klic zustava vzdy jen na serveru.

## Odpovednosti

- `OpenAiApi` kontroluje Rokid klic a rate limit; tenant dostava z routeru.
- `OpenAiRealtimeService` vola `POST /v1/realtime/client_secrets`.
- `OpenAiCatalogGateway` oddeluje domenu od uloziste.
- `OpenAiCatalogRepository` nacita publikovana tenantova data pres existujici moduly.
- `OpenAiProductDocumentBuilder` vytváří stabilní produktové JSON dokumenty.
- `OpenAiVectorStoreSyncService` inkrementálně nahrává změněné produkty a odstraňuje
  indexy produktů, které už nejsou publikované.
- `OpenAiVectorProductRetrieval` vrací Realtime modelu syrové výsledky OpenAI
  Retrieval API bez lokálního filtrování nebo řazení.
- `OpenAiVectorStoreRepository` drží tenantové ID indexu a vazbu produktu na
  OpenAI soubor; tajný OpenAI klíč ani obsah rozhovoru neukládá.

Realtime model lze bez změny kódu nastavit přes `OPENAI_REALTIME_MODEL`; výchozí
hodnota je `gpt-realtime`. Systémové instrukce a popisy nástrojů jsou stručně
strukturované v angličtině, analyzovaný rozhovor však zůstává český. Jazyk instrukcí sám o sobě negarantuje
nižší cenu ani vyšší přesnost; rozhodující je jejich jednoznačnost, délka a
ověření na reálných dialozích. Prompt je tenantově neutrální a konkrétní profily
i produkty vždy pocházejí z hostem vybraného katalogu.
- `OpenAiKnowledgeCatalogService` validuje úzký retrieval/detail kontrakt, ale
  nerozhoduje o vhodnosti produktu.
- Chyby upstreamu se mapuji na obecny stav 502 a nikdy nevraceji telo OpenAI
  odpovedi ani serverovy API klic.

## Testovani

Offline unit test nepouziva skutecny OpenAI ucet:

```bash
php8.2 src/Modules/OpenAi/tests/OpenAiRealtimeServiceTest.php
php8.2 src/Modules/OpenAi/tests/OpenAiKnowledgeCatalogServiceTest.php
php8.2 src/Modules/OpenAi/tests/OpenAiVectorStoreTest.php
```

## První synchronizace a pravidelná aktualizace

Na existující databázi se nejprve spustí aditivní migrace:

```bash
mysql -h "$DB_HOST" -u "$DB_USER" -p "$DB_NAME" \
  < migrations/20260923_openai_vector_store.sql
```

Po nastavení serverového `OPENAI_API_KEY` vytvoří první příkaz tenantový Vector
Store a nahraje publikované produkty. Další běhy jsou inkrementální podle
SHA-256 otisku dokumentu:

```bash
php8.2 scripts/sync_openai_vector_store.php --tenant=fun
```

Teprve po úspěšné první synchronizaci se pro běžné API nastaví
`OPENAI_VECTOR_STORE_ENABLED=true`. Synchronizaci lze spouštět po katalogovém
importu nebo pravidelně z cronu, například jednou za hodinu. Souběžné běhy nad
stejným tenantem se nesmějí plánovat. Při změně produktu vznikne nejprve nový
hotový index a až poté se odpojí starý soubor, takže běžné vyhledávání nepřijde
o poslední platnou verzi.

Zivy smoke test vyzaduje produkcni env hodnoty a platny tenant host. Hodnoty
tajnych hlavicek nevypisuj do logu ani je neukladej do shell historie.
