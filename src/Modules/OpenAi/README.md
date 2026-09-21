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
vystup a limit 64 output tokenu. Deklaruje funkce `list_customer_profiles`,
`search_products`, `get_product` a `show_customer_question`; model jejich
provedeni pouze vyzada a mobil je vykona pres nasledujici backendovy endpoint.

```http
POST /api/openai/tool
X-Rokid-Key: <ROKID_AI_CLIENT_KEY>
Content-Type: application/json

{"name":"search_products","arguments":{"profile_id":11,"max_price":1500,"limit":3}}
```

Katalogovy endpoint je tenantove omezeny, rate-limitovany na 60 volani za
minutu a nezpristupnuje obecne admin API. Vraci pouze publikovane profily a
produkty; nejvyse pet produktu radi podle profilove pravdepodobnosti a textove
shody. `excluded_product_ids` odstrani drive zobrazene polozky, aby prubezne
doporucovani po namitce neopakovalo stejny produkt. `get_product` vraci detail
pouze publikovane polozky.

Repository čte profily a produkty po databázových stránkách a vystavuje je jako
`iterable`, takže katalog není potichu omezený na prvních 100 záznamů. Vyhledání
drží v paměti pouze nejlepší požadovaný počet kandidátů, nikoli celý katalog.

Realtime relace analyzuje cely rozhovor a neposila volny text urceny k
zobrazeni. Pokud chybi jedna podstatna informace, muze pres validovanou funkci
zobrazit jednu kratkou otazku a pocka na dalsi promluvu. Po dostatku informaci
nacte profily, vyhleda kandidaty a overi detail produktu. Nova namitka muze
spustit dalsi hledani s vyloucenim drive zobrazenych produktu.

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
hodnota je `gpt-realtime`. Prompt je tenantově neutrální a konkrétní profily i
produkty vždy pocházejí z hostem vybraného katalogu.
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
