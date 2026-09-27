<?php

declare(strict_types=1);
require dirname(__DIR__, 4).'/vendor/autoload.php';

use App\Modules\Http\{HttpModule, HttpService, HttpRequest, HttpResponse, HttpException, SmtpService};
use App\Modules\FannCatalog\FannCatalogProvider;
use App\Modules\OpenAi\{OpenAiVectorStoreProvider, OpenAiRealtimeService, OpenAiUpstreamException};
use App\Modules\Sry\{CloudflareGateway, SryError};
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPMailer\PHPMailer\PHPMailer;

error_reporting(E_ALL);
set_error_handler(static function (int $n, string $s, string $f, int $l): bool {
    if (!(error_reporting() & $n)) {
        return false;
    }
    throw new ErrorException($s, 0, $n, $f, $l);
});
$count = 0;
function check(bool $ok, string $label): void
{
    global $count;
    if (!$ok) {
        throw new RuntimeException('FAIL '.$label);
    }
    ++$count;
    echo 'PASS '.$label.PHP_EOL;
}
function rejects(callable $fn, string $type, string $label): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($e instanceof $type, $label);
        return;
    }
    throw new RuntimeException('Expected exception: '.$label);
}
function mockHttp(array $responses, array &$history): HttpService
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new HttpService(new Client(['handler' => $stack]));
}
$http = HttpModule::client();
check($http === HttpModule::client(), 'module reuses stateless HTTP client');
check($http->sendAll([]) === [], 'empty batch');
rejects(fn () => $http->sendAll([], 0), InvalidArgumentException::class, 'reject invalid batch deadline');
rejects(fn () => new HttpRequest('https://example.test', body: [], multipart: []), InvalidArgumentException::class, 'reject ambiguous upload encoding');
check($http->send(new HttpRequest('file:///etc/hosts'))->error === 'invalid_endpoint', 'reject non HTTP protocol');
check($http->send(new HttpRequest('https://secret:password@example.test'))->error === 'invalid_endpoint', 'reject credentials in URL');
$history = [];
$mock = mockHttp([new Response(200, [], '{"errors":["business field"]}')], $history);
check($mock->send(new HttpRequest('https://example.test'))->json()['errors'] === ['business field'], 'generic JSON does not interpret GraphQL business errors');
rejects(fn () => (new HttpResponse(200, 'null'))->json(), HttpException::class, 'reject scalar JSON');
rejects(fn () => (new HttpResponse(503, '{}'))->json(), HttpException::class, 'HTTP status mapped independently from network errors');

$processes = [];
$directory = sys_get_temp_dir().'/php-core-http-'.bin2hex(random_bytes(6));
mkdir($directory, 0700);
try {
    $bases = [];
    for ($i = 0; $i < 2; ++$i) {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open([PHP_BINARY, '-S', $address, __DIR__.'/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start fixture server');
        }
        $processes[] = [$process, $pipes[0]];
        $base = 'http://'.$address;
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            if ($http->send(new HttpRequest($base.'/echo', timeoutMs: 100))->status === 200) {
                break;
            }
            usleep(20000);
        }
        $bases[] = $base;
    }
    [$base, $second] = $bases;
    $data = $http->send(new HttpRequest($base.'/echo', 'POST', ['Authorization: Bearer tenant-a'], ['text' => 'Žluťoučký', 'ok' => true]))->json();
    check($data['method'] === 'POST' && json_decode($data['body'], true)['text'] === 'Žluťoučký', 'real JSON POST and Unicode');
    check(($data['headers']['Authorization'] ?? '') === 'Bearer tenant-a', 'legacy and associative headers supported');
    $data = $http->send(new HttpRequest($base.'/echo'))->json();
    check(!isset($data['headers']['Authorization']), 'credentials never leak to next tenant request');
    $data = $http->send(new HttpRequest($base.'/echo', 'PUT', ['Content-Type' => 'application/xml'], '<root/>'))->json();
    check($data['method'] === 'PUT' && $data['body'] === '<root/>', 'raw XML/PUT transport');
    $data = $http->send(new HttpRequest($base.'/echo', 'POST', multipart: [
        ['name' => 'purpose', 'contents' => 'assistants'],
        ['name' => 'file', 'contents' => '{"č":1}', 'filename' => 'catalog.json', 'headers' => ['Content-Type' => 'application/json']],
    ]))->json();
    check($data['post']['purpose'] === 'assistants' && $data['files']['file'] === ['name' => 'catalog.json', 'type' => 'application/json', 'content' => '{"č":1}'], 'real multipart upload preserves file metadata and bytes');
    $result = $http->sendAll([
        'large' => new HttpRequest($base.'/large', maxBytes: 100),
        'gzip' => new HttpRequest($second.'/gzip', maxBytes: 100),
        'rate' => new HttpRequest($base.'/rate'),
        'redirect' => new HttpRequest($second.'/redirect'),
        'invalid' => new HttpRequest($base.'/invalid'),
    ]);
    check(array_keys($result) === ['large', 'gzip', 'rate', 'redirect', 'invalid'], 'batch preserves caller keys/order');
    check($result['large']->error === 'response_too_large', 'hard body limit without Content-Length');
    check($result['gzip']->error === 'response_too_large', 'body limit applies after gzip decompression');
    check($result['rate']->status === 429 && $result['rate']->retryAfter === 120 && $result['rate']->error === null, '429 and Retry-After retained');
    check($result['redirect']->status === 302, 'redirects disabled by default');
    rejects(fn () => $result['invalid']->json(), HttpException::class, 'invalid upstream JSON');
    $target = $directory.'/feed.zip';
    $result = $http->send(new HttpRequest($base.'/large', sink: $target));
    check($result->successful() && $result->body === '' && filesize($target) === 100000, 'download streams into file without response body copy');
    $result = $http->send(new HttpRequest($base.'/large', maxBytes: 100, sink: $target));
    clearstatcache(true, $target);
    check($result->error === 'response_too_large' && filesize($target) <= 100, 'download byte limit');
    $start = microtime(true);
    $results = $http->sendAll(['a' => new HttpRequest($base.'/slow'), 'b' => new HttpRequest($second.'/slow')], 2000, 2);
    check($results['a']->successful() && $results['b']->successful() && microtime(true) - $start < 0.55, 'requests really run concurrently');
    $start = microtime(true);
    $results = $http->sendAll(['a' => new HttpRequest($base.'/slow'), 'b' => new HttpRequest($second.'/slow'), 'queued' => new HttpRequest($base.'/slow')], 100, 1);
    check(microtime(true) - $start < 0.3 && count(array_filter($results, fn ($r) => $r->error !== null)) === 3, 'shared deadline includes waiting jobs');
    // Keep another provider usable after one request fails.
    $results = $http->sendAll(['bad' => new HttpRequest('file:///etc/hosts'), 'good' => new HttpRequest($second.'/echo')]);
    check($results['bad']->error !== null && $results['good']->successful(), 'one provider failure does not abort the batch');
} finally {
    foreach ($processes as [$process, $pipe]) {
        proc_terminate($process);
        fclose($pipe);
        proc_close($process);
    }
    foreach (glob($directory.'/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}

$history = [];
$mock = mockHttp([new Response(302, ['Location' => '/next']), new Response(200, ['Content-Type' => 'text/html'], '<p>ok</p>')], $history);
check((new FannCatalogProvider(http: $mock))->get('https://www.fann.cz/start') === '<p>ok</p>' && count($history) === 2, 'FAnn follows same-origin HTTPS redirect');
check($history[1]['options']['timeout'] <= $history[0]['options']['timeout'], 'redirect shares original deadline');
$history = [];
$mock = mockHttp([new Response(302, ['Location' => 'https://other.test/steal'])], $history);
rejects(fn () => (new FannCatalogProvider(http: $mock))->get('https://www.fann.cz/start'), RuntimeException::class, 'FAnn blocks cross-origin redirect');
check(count($history) === 1, 'rejected redirect is never requested');
$history = [];
$mock = mockHttp([new Response(302, ['Location' => 'http://www.fann.cz/next'])], $history);
check($mock->send(new HttpRequest('https://www.fann.cz/start', redirectHosts: ['www.fann.cz']))->error === 'redirect_rejected', 'redirect cannot downgrade TLS');
$history = [];
$mock = mockHttp(array_fill(0, 4, new Response(302, ['Location' => '/loop'])), $history);
check($mock->send(new HttpRequest('https://www.fann.cz/start', redirectHosts: ['www.fann.cz']))->error === 'redirect_rejected' && count($history) === 4, 'redirect count bounded');
$history = [];
$mock = mockHttp([new Response(200, ['Content-Type' => 'text/html'], 'a'), new Response(200, ['Content-Type' => 'application/json'], '{}')], $history);
rejects(fn () => (new FannCatalogProvider(http: $mock))->getMany(['https://www.fann.cz/a', 'https://www.fann.cz/b']), RuntimeException::class, 'parallel FAnn fetch validates content type');
rejects(fn () => (new FannCatalogProvider())->get('https://evil.test'), RuntimeException::class, 'FAnn restricts initial host');

$history = [];
$mock = mockHttp([new Response(200, [], '{"id":"file-1"}')], $history);
check((new OpenAiVectorStoreProvider(apiKey: 'tenant-secret', http: $mock))->uploadJson('catalog.json', '{"ok":true}')['id'] === 'file-1', 'Vector Store uses shared multipart transport');
$upload = $history[0]['request'];
check(str_contains($upload->getHeaderLine('Content-Type'), 'multipart/form-data; boundary=') && str_contains((string)$upload->getBody(), 'filename="catalog.json"') && $upload->getHeaderLine('Authorization') === 'Bearer tenant-secret', 'Vector Store payload/auth preserved');
$history = [];
$mock = mockHttp([new Response(503, [], 'secret upstream diagnostic')], $history);
rejects(fn () => (new OpenAiRealtimeService(apiKey: 'secret', http: $mock))->createClientSecret(), OpenAiUpstreamException::class, 'OpenAI upstream failure keeps domain exception');
$history = [];
$mock = mockHttp([new Response(200, [], '{"ok":true}'), new Response(503, [], 'secret')], $history);
$cloud = new CloudflareGateway('https://media.example', str_repeat('a', 32), $mock);
check($cloud->call('/publish', ['op' => 'publish'], ['id' => 12])['ok'], 'Cloudflare uses shared transport');
parse_str($history[0]['request']->getUri()->getQuery(), $query);
$claims = json_decode(base64_decode(strtr(explode('.', $query['ticket'])[0], '-_', '+/')), true);
check($claims['tenant'] === 'sry' && $claims['exp'] > time() && json_decode((string)$history[0]['request']->getBody(), true)['id'] === 12, 'Cloudflare signed ticket/body preserved');
rejects(fn () => $cloud->call('/media', []), SryError::class, 'Cloudflare failure keeps domain exception');

// Test message assembly/configuration without sending email to any recipient.
$_ENV['MAILER_FROM'] = 'global@example.test';
$_ENV['MAILER_SMTP_HOST'] = 'global.smtp.test';
$_ENV['MAILER_SMTP_PASS'] = 'global-pass';
$_ENV['TRAM_MAILER_FROM'] = 'tram@example.test';
$_ENV['TRAM_MAILER_FROM_NAME'] = 'TRAM';
$_ENV['TRAM_MAILER_SMTP_HOST'] = 'tram.smtp.test';
$_ENV['TRAM_MAILER_SMTP_USER'] = 'tram-user';
$_ENV['TRAM_MAILER_SMTP_PASS'] = 'tram-pass';
$_ENV['TRAM_MAILER_SMTP_SECURE'] = 'ssl';
$_ENV['TRAM_MAILER_SMTP_PORT'] = '465';
$messages = [];
$factory = static function () use (&$messages): PHPMailer {
    $mail = new class (true) extends PHPMailer {
        public function send()
        {
            return $this->preSend();
        }
    };
    $messages[] = $mail;
    return $mail;
};
$smtp = new SmtpService('tram', $factory);
$mailer = new App\Modules\Mailer\MailerService('tram', $smtp);
check($mailer->sendMail(['one@example.test', 'two@example.test'], 'Příliš žluťoučký', 'test', ['email' => 'reader@example.test', 'logoPath' => 'logo', 'fromEmail' => 'reply@example.test'], [], 'hidden@example.test'), 'Mailer renders and submits two separate messages');
check(count($messages) === 2 && count($messages[0]->getToAddresses()) === 1 && $messages[0]->getToAddresses()[0][0] !== $messages[1]->getToAddresses()[0][0], 'recipient privacy unchanged');
$mail = $messages[0];
check($mail->Host === 'tram.smtp.test' && $mail->Username === 'tram-user' && $mail->Password === 'tram-pass' && $mail->Port === 465 && $mail->SMTPSecure === PHPMailer::ENCRYPTION_SMTPS, 'tenant SMTP credentials and TLS preserved');
check($mail->From === 'tram@example.test' && ($mail->getReplyToAddresses()[0][0] ?? '') === 'reply@example.test' && count($mail->getBccAddresses()) === 1 && $mail->CharSet === 'UTF-8', 'sender/reply-to/BCC/UTF-8 preserved');
$global = new SmtpService('', $factory);
$global->send('reader@example.test', 'subject', 'body', [], null);
check(end($messages)->Host === 'global.smtp.test' && end($messages)->From === 'global@example.test', 'SMTP settings do not leak between tenants');
$_ENV['MAILER_SMTP_HOST'] = '';
(new SmtpService('', $factory))->send('reader@example.test', 'subject', 'body', [], null);
check(end($messages)->Mailer === 'mail', 'native mail fallback preserved');
echo "HTTP/SMTP integration: $count assertions passed\n";
