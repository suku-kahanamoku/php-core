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
Povinné volání nástroje dovoluje pouze `recommend_product`, `get_product` a lokální
`continue_listening`; model proto nemůže místo výběru produktu vrátit volný text.
Katalogové nástroje mobil vykoná přes následující backendový endpoint.

```http
POST /api/openai/tool
X-Rokid-Key: <ROKID_AI_CLIENT_KEY>
Content-Type: application/json

{"name":"recommend_product","arguments":{"query":"svěží parfémová voda na každý den do 2500 Kč","category":"parfém","price_intent":"maximálně 2500 Kč"}}
```

Katalogový endpoint je tenantově omezený, rate-limitovaný na 60 volání za
minutu a nezpřístupňuje obecné admin API. PHP neposuzuje rozhovor, nefiltruje
produkty podle požadavků a nevytváří vlastní pořadí kandidátů.

`recommend_product` vyžaduje český `query`, konkrétní `category` a potvrzený
`price_intent`. PHP je pouze validuje a předá OpenAI Responses modelu. Ten
pomocí hostovaného `file_search` vyhledá dokumenty v tenantovém Vector Store,
sám porovná názvy, popisy, kategorie, varianty, cenu, dostupnost a
`selection_attributes` a vrátí pouze stav a konkrétní `product_id`. PHP
nepřijímá ani nevrací pořadí kandidátů.

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
provede `file_search`, rozhodne a vrátí jediné doložené ID. Teprve následné
`get_product` načte právě jeden publikovaný produkt z databáze.

Realtime relace analyzuje celý rozhovor a neposílá volný text určený k
zobrazení. Rozlišuje osobu a nákupní záměr, potvrzená fakta, jednoznačně
vyjádřený význam, hypotézy a neznámé údaje. Nikdy nepokládá otázku a negeneruje
prodejní argument, upsell ani cross-sell. Hledání začne až tehdy, když je známá
konkrétní kategorie produktu a současně cenový záměr nebo důvěryhodný
normalizovaný profil. Obecné „produkt“, „kosmetika“ ani účel „dárek“ nejsou
kategorií. Profilový kontext zatím Realtime relaci není zpřístupněný, takže v
aktuálním kontraktu musí být potvrzená kategorie i cena. Do té doby relace volá
`continue_listening`. Responses model z `file_search` dokumentů vybere jediný produkt a PHP
pro něj pouze načte aktuální katalogový detail. Nespokojenost nebo žádost o jiný,
další či lepší produkt vede bezprostředně k preferenci jiné vhodné varianty,
ale žádné dříve zobrazené ID se trvale nevyloučí. Zákazník se k němu může později
vrátit. „Lepší“ znamená přesnější shodu s doloženými požadavky, nikoli vyšší
cenu, popularitu nebo marži.

Migrace `migrations/20260921_fun_product_catalog_enrichment.sql` idempotentně
obohacuje 23 dohledaných existujících FAnn produktů a přidává 30 aktuálních variant. Ukládá
zdrojovou URL, datum kontroly, značku, variantu, EAN, diagnostické otázky a
normalizované výběrové atributy v `data`; historický seed nemění.

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

- `OpenAiApi` kontroluje Rokid klic a rate limit; tenant dostava z routeru.
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
  < migrations/20260923_openai_vector_store.sql
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
stejným tenantem se nesmějí plánovat. Při změně produktu vznikne nejprve nový
hotový index a až poté se odpojí starý soubor, takže běžné vyhledávání nepřijde
o poslední platnou verzi.

Zivy smoke test vyzaduje produkcni env hodnoty a platny tenant host. Hodnoty
tajnych hlavicek nevypisuj do logu ani je neukladej do shell historie.
