<?php

declare(strict_types=1);
namespace App\Modules\Etymolog;
use App\Modules\Router\{Request,Response};

/** Machine-only route: internal API authentication PLUS a separate tenant-bound secret. */
final class EtymologWorkerApi
{
    public function __construct(private readonly EtymologHttpWorkerService $service) {}
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
