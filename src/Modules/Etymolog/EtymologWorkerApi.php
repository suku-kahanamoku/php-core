<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;
use App\Modules\Router\{Request,Response};

/**
 * Routa výhradně pro stroje: interní autentizace API PLUS samostatný klíč vázaný na okurk.
 *
 * Klíč v hlavičce `X-Etymolog-Worker-Key` se porovnává v konstantním čase a
 * endpoint je navíc zcela vypnutý, pokud `ETYMOLOG_SYNC_DISPATCH` není
 * 'cloudflare'. Tělo požadavku je omezené na 512 bajtů a jeho klíče musí
 * přesně odpovídat očekávané sadě pro danou akci.
 */
final class EtymologWorkerApi
{
    /**
     * @param  EtymologHttpWorkerService $service Služby workera synchronizace.
     * @return void
     */
    public function __construct(private readonly EtymologHttpWorkerService $service) {}

    /**
     * Obslouží požadavek workera a vždy požadavek ukončí.
     *
     * @param  Request $r Aktuální požadavek.
     * @return never     Vždy ukončí požadavek přes `Response::success()` nebo `Response::error()`.
     */
    public function handle(Request $r): never
    {
        try {
            $key = $_ENV['ETYMOLOG_SYNC_SECRET'] ?? '';
            if (!$r->internalAuthenticated || $r->franchiseCode !== 'etymolog' || strlen($key) < 32 || !hash_equals($key, (string)$r->header('X-Etymolog-Worker-Key', ''))) {
                throw new EtymologException('Worker authentication required', 403);
            }
            if (($_ENV['ETYMOLOG_SYNC_DISPATCH'] ?? '') !== 'cloudflare') { throw new EtymologException('HTTP worker disabled', 503); }
            if (strlen(json_encode($r->body, JSON_THROW_ON_ERROR)) > 512) { throw new EtymologException('Worker request too large', 413); }
            $body = $r->body; $action = $body['action'] ?? null;
            $expected = match ($action) { 'health' => ['action'], 'nightly' => ['action','date'], 'step' => ['action','request_id','step'], default => throw new EtymologException('Invalid worker action', 422) };
            if (count($body) !== count($expected) || array_diff(array_keys($body), $expected)) { throw new EtymologException('Invalid worker fields', 422); }
            if ($action === 'health') { $result = ['status' => 'ready']; }
            elseif ($action === 'nightly' && is_string($body['date'])) { $result = $this->service->nightly($body['date']); }
            elseif ($action === 'step' && is_string($body['request_id']) && preg_match('/^[a-f0-9]{32}$/D', $body['request_id']) && is_int($body['step']) && $body['step'] >= 0 && $body['step'] <= 2147483647) { $result = $this->service->step($body['request_id'], $body['step']); }
            else { throw new EtymologException('Invalid worker parameters', 422); }
            header('Cache-Control: private, no-store');
            Response::success($result);
        } catch (EtymologException $e) { Response::error($e->getMessage(), $e->status); }
        exit;
    }
}
