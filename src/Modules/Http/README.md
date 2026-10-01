# HttpModule

Společné místo pro odchozí síťovou komunikaci php-core. HTTP integrace používají
`Contracts/HttpClient`, jehož jedinou produkční implementací je `HttpService`
postavená na Guzzle. SMTP/native mail řeší `SmtpService` přes PHPMailer.
`HttpModule` vytváří tyto služby; doménové služby přijímají jejich rozhraní v konstruktoru.

## Použití

```php
use App\Modules\Http\{HttpModule, HttpRequest};

$response = HttpModule::client()->send(new HttpRequest(
    'https://provider.example/api/search',
    'POST',
    ['Authorization' => 'Bearer '.$serverToken],
    ['from' => $from, 'to' => $to],
    timeoutMs: 5000,
));
$data = $response->json();
```

Pro doménovou službu použít injekci `Contracts\HttpClient`. Volitelný argument
může jako výchozí hodnotu použít `HttpModule::client()`. Instance klienta je
sdílená, ale klíče, hlavičky a těla jsou vždy součástí konkrétního `HttpRequest`.
Neukládat tenantové klíče do globální konfigurace Guzzle.

- `send()` provede jeden požadavek.
- `sendAll($requests, $budgetMs, $concurrency)` zachová klíče a pořadí výsledků;
  počet aktivních přenosů je omezený (výchozí 4, nejvýše 32). Deadline zahrnuje
  čekání ve frontě. Selhání jednoho zdroje neukončí ostatní požadavky.
- `HttpRequest::body`: pole se odešle jako JSON, řetězec jako raw body; `null`
  znamená bez těla. `multipart` přijímá části s `name`, `contents`, `filename`, `headers`.
- `sink` zapisuje odpověď do soukromého lokálního souboru. `HttpResponse::body`
  je potom prázdné. Volající musí použít dočasný soubor a při chybě ho odstranit;
  cílová cesta se při zahájení přenosu přepisuje. `FeedSyncService` tuto správu zajišťuje.
- Výchozí limity: přenos 4 s, připojení 1,5 s, odpověď 4 MB. GTFS explicitně používá
  180 s / 10 s / 500 MB a souborový sink. Limit počítá rozbalené bajty, takže platí
  i pro gzip a odpovědi bez Content-Length.
- TLS se ověřuje; cookies a přesměrování jsou vypnuté. FAnn výslovně povoluje nejvýše
  3 HTTPS přesměrování na stejný origin `www.fann.cz`, v rámci původního deadline.
- Síťové chyby jsou výsledkem s `error`; HTTP 4xx/5xx zachovají status, hlavičky
  a `Retry-After`. `json()` vyžaduje 2xx a JSON pole/objekt, jinak vyhodí `HttpException`.
  Chyby obsahují jen obecný kód, ne tajné URL, hlavičky či tělo upstream výjimky.
- Automatické opakování požadavků není zapnuté. Retry, circuit breaker a pravidla
  idempotence patří integrační/doménové službě (například outbox nebo Transport).
- Interní adresy OTP jsou legitimní, proto není globálně zakázán localhost.
  Každý provider musí omezit adresy vlastní konfigurací/allowlistem; klient není
  veřejné API pro libovolné URL od uživatele.

Guzzle má explicitní `CurlMultiHandler`, takže paralelismus nepadne na synchronní
PHP stream handler. Čekání na sockety obstarává knihovna; v modulech není vlastní
`curl_multi_*` smyčka. Jde o dokončení jedné omezené dávky, nikoli nekonečný polling.
[Guzzle async a Pool](https://docs.guzzlephp.org/en/stable/quickstart.html#concurrent-requests),
[volby požadavků](https://docs.guzzlephp.org/en/stable/request-options.html),
[handlery a middleware](https://docs.guzzlephp.org/en/stable/handlers-and-middleware.html).

## Integrace a další protokoly

Přepojené: Entur/OTP, PID/Golemio, stahování GTFS, FAnn katalog, OpenAI Realtime
založení session, Responses, Vector Store upload, Cloudflare a Expo push outbox.
Stejné rozhraní používají i pomocníci API testů.

`MailerService` nadále řeší šablony a jednotlivé příjemce. Síťovou konfiguraci,
PHPMailer a odeslání vlastní `SmtpService` (`Contracts/MailClient`), vždy pro jeden
franchise code. Existující `*_MAILER_*` a globální fallback zůstávají funkční.

WebSocket server implementuje `WebSocketService` přes `Contracts/WebSocketServer`
a Workerman. SOAP ani odchozí WebSocket klient zatím implementované nejsou.
Mobilní WebSocket spojení s OpenAI/Cloudflare není PHP socket. Další protokoly
patří se svým rozhraním do tohoto modulu; Guzzle nepředstírá implementaci WebSocketu. SOAP
může používat XML body přes HTTP, ale WSDL klient musí mít vlastní adapter. Pro
trvalé WebSocket/SSE spojení je třeba odpovídající worker a lifecycle, ne běžný
krátký PHP request. PDO a lokální filesystem zůstávají v příslušné infrastruktuře.
Dompdf má vzdálené zdroje vypnuté.

## Názvy a kompatibilita

Repository končí `Repository`, služby `Service`, adaptéry `Provider`, registry
`Registry`, HTTP endpointy `Api`. Datové typy a pomocné role používají odpovídající
názvy `Request`, `Response`, `Exception`, `Mapper`, `Codec`, `Reader` nebo DTO.

Přepojené interní názvy (včetně všech použití v projektu):

| Dříve | Nyní |
| --- | --- |
| Transport/Http/CurlHttpClient | Http/HttpService přes HttpModule |
| Transport/Contracts/HttpClient | Http/Contracts/HttpClient |
| Transport/Http/HttpRequest, HttpResult | Http/HttpRequest, HttpResponse |
| FannCatalogHttpClient | FannCatalogProvider |
| OpenAiVectorStoreClient | OpenAiVectorStoreProvider |
| SryStore | SrySqlRepository |
| Configuration | ConfigurationService |
| GraphManager | GraphService |
| GtfsImporter, GtfsArchive | GtfsImportService, GtfsArchiveReader |
| ServiceClock | ServiceTimeService |
| ResourceId, Geometry | ResourceIdCodec, GeometryMapper |

HTTP endpointy ani jejich autentizační smlouvy se tímto refaktorem nemění.
Při nasazení spustit `composer install` podle lockfile a obnovit PHP OPcache/workery.
Je potřeba PHP curl extension. Refaktor nevyžaduje databázovou migraci.

## Kontroly

```bash
composer install
bash scripts/test-http.sh
bash scripts/test-transport.sh
# Volitelně skutečné čtecí požadavky na Entur:
php src/Modules/Transport/tests/live.php
```

HTTP testy používají dva lokální fixture servery a Guzzle MockHandler; SMTP zprávy
sestavují, ale neodesílají. Transport test má vlastní dočasnou MySQL. Aplikační ani
produkční databáze se při těchto testech nepoužívá.

### Asynchronní HTTP a WebSocket gateway

`HttpModule::asyncClient()` vrací `AsyncHttpClient` pro Workerman event loop (`sendAsync` s jedním callbackem `HttpResponse`). Jeho synchronní metody delegují běžnému klientu; v gateway se nepoužívají. TLS ověřuje certifikát, přesměrování jsou zakázána, platí limity času a velikosti těla. Pool se vytváří až uvnitř aktivního event loopu. `HttpModule::websocket()` vrací `Contracts\WebSocketServer`; výchozí `WebSocketService` obsluhuje loopback listener, origin allowlist, velikost zpráv, heartbeat timeout a pomalé klienty. Transportová doména dodává pouze callbacky a vlastní pravidla odběrů.


Kontrakt serveru zachovává `run(listen, origins, message, closed, tick, name, runtimeDirectory)`.
Callbacky používají pouze řetězcová ID, JSON pole a funkce send/close; nepropouštějí
objekty Workerman do domény. Továrna server pouze vytvoří, `run()` spouští jeho
životní cyklus. Jiný server lze zvolit v `HttpModule`; spotřebitelům se injektuje
`Contracts\WebSocketServer`. Testovací implementace může uchovat callbacky a
řízeně simulovat zprávy, heartbeat a uzavření bez portu nebo event loopu.
Transport test ověřuje tímto mockem skutečný `TrackingHubService`; nejde o test
WebSocket handshaku nové implementace. Kontrakt nemění konfiguraci ani způsob
spuštění `bin/transport-tracking.php` a nevyžaduje migraci DB.
