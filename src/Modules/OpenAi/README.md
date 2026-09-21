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

Token plati 60 sekund pro vytvoreni relace. Relace pouziva server VAD, textovy
vystup a limit 256 output tokenu pro bezpečné dokončení strukturovaných argumentů.
Povinné volání nástroje dovoluje pouze `search_products`, `get_product` a lokální
`continue_listening`; model proto nemůže místo výběru produktu vrátit volný text.
Katalogové nástroje mobil vykoná přes následující backendový endpoint.

```http
POST /api/openai/tool
X-Rokid-Key: <ROKID_AI_CLIENT_KEY>
Content-Type: application/json

{"name":"search_products","arguments":{"category":"Parfémy","required_attributes":["parfémová voda"],"preferred_attributes":["svěží","každodenní"],"max_price":2500,"limit":3}}
```

Katalogovy endpoint je tenantove omezeny, rate-limitovany na 60 volani za
minutu a nezpristupnuje obecne admin API. Vraci pouze publikovane profily a
produkty. Povinné atributy, cenový strop, kategorie, výslovné zákazy a odmítnutá
ID fungují jako tvrdé podmínky. Nejvýše pět způsobilých produktů se řadí podle
toho, zda ještě nebyly zobrazeny, podle kladných a záporných preferencí, textové
shody a až nakonec podle úplnosti atributů. Profilová pravděpodobnost se
do primárního výběru nezapočítává ani se v jeho výsledku nevrací; zůstává
uložená pro případnou samostatnou upsell logiku. `displayed_product_ids` pouze
sníží prioritu opakované nabídky; `rejected_product_ids` produkt tvrdě odstraní.
Starší `attributes` a `excluded_product_ids` zůstávají kompatibilními aliasy.
`get_product` znovu přijímá aktuální povinné atributy, zákazy, kategorii a
cenový strop. Detail vrátí pouze publikované a dostupné položce, která všemi
tvrdými kontrolami projde; jinak vrátí `product: null` a seznam porušení.

`search_products` přijímá `query`, `category`, `max_price`, `limit`, nejvýše 12
hodnot v každém z polí `required_attributes`, `preferred_attributes`,
`negative_preferences` a `excluded_attributes` a nejvýše 50 ID v
`displayed_product_ids` a `rejected_product_ids`. Produkt bez všech povinných
atributů nebo se shodou na výslovném zákazu je z výsledků vyřazen.
Vyhledávání porovnává atributy s názvem, popisem, variantou, kategorií a JSON
`data.selection_attributes`; ve výsledku vrací zvlášť splněné povinné a kladné
atributy, rozpory s měkkými preferencemi, chybějící preference, celkový počet
způsobilých kandidátů a stav `candidates` nebo `no_match`.

Repository čte profily a produkty po databázových stránkách a vystavuje je jako
`iterable`, takže katalog není potichu omezený na prvních 100 záznamů. Vyhledání
drží v paměti pouze nejlepší požadovaný počet kandidátů, nikoli celý katalog.

Realtime relace analyzuje celý rozhovor a neposílá volný text určený k
zobrazení. Rozlišuje osobu a nákupní záměr, potvrzená fakta, jednoznačně
vyjádřený význam, hypotézy a neznámé údaje. Nikdy nepokládá otázku a negeneruje
prodejní argument, upsell ani cross-sell. Jakmile zachytí libovolný použitelný
nákupní signál, hledá ihned; neznámé vlastnosti ponechá bez omezení. Jen čisté
pozadí, nedokončená řeč nebo nezměněný stav ukončí lokálním nástrojem
`continue_listening`. Před zobrazením se načte detail jediného produktu a ověří
varianta, cena, dostupnost a tvrdé podmínky. Nový produkt se zobrazí jen tehdy,
když nové informace změní nejlepší shodu nebo zneplatní současnou. Zákaznické
profily nejsou Realtime relaci zpřístupněné.

Migrace `migrations/20260921_fun_product_catalog_enrichment.sql` idempotentně
obohacuje 23 dohledaných existujících FAnn produktů a přidává 30 aktuálních variant. Ukládá
zdrojovou URL, datum kontroly, značku, variantu, EAN, diagnostické otázky a
normalizované výběrové atributy v `data`; historický seed nemění.

## Konfigurace

```dotenv
OPENAI_API_KEY=sk-proj-...
ROKID_AI_CLIENT_KEY=<nahodny retezec alespon 32 bytu>
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

Realtime model lze bez změny kódu nastavit přes `OPENAI_REALTIME_MODEL`; výchozí
hodnota je `gpt-realtime`. Systémové instrukce a popisy nástrojů jsou stručně
strukturované v angličtině, analyzovaný rozhovor však zůstává český. Jazyk instrukcí sám o sobě negarantuje
nižší cenu ani vyšší přesnost; rozhodující je jejich jednoznačnost, délka a
ověření na reálných dialozích. Prompt je tenantově neutrální a konkrétní profily
i produkty vždy pocházejí z hostem vybraného katalogu.
- `OpenAiCatalogService` validuje povolene funkce, filtry a razeni vysledku.
- Chyby upstreamu se mapuji na obecny stav 502 a nikdy nevraceji telo OpenAI
  odpovedi ani serverovy API klic.

## Testovani

Offline unit test nepouziva skutecny OpenAI ucet:

```bash
php8.2 src/Modules/OpenAi/tests/OpenAiRealtimeServiceTest.php
php8.2 src/Modules/OpenAi/tests/OpenAiCatalogServiceTest.php
```

Zivy smoke test vyzaduje produkcni env hodnoty a platny tenant host. Hodnoty
tajnych hlavicek nevypisuj do logu ani je neukladej do shell historie.
