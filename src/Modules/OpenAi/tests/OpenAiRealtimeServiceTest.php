#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Offline test konfigurace a parsovani OpenAI Realtime client secretu. */

use App\Modules\OpenAi\OpenAiConfigurationException;
use App\Modules\OpenAi\OpenAiRealtimeService;
use App\Modules\OpenAi\OpenAiUpstreamException;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';

section('OpenAI Realtime service');
$captured = null;
$service = new OpenAiRealtimeService(
    static function (string $apiKey, array $payload) use (&$captured): array {
        $captured = ['apiKey' => $apiKey, 'payload' => $payload];
        return [
            'status' => 200,
            'body' => json_encode([
                'value' => 'ek_test_secret',
                'expires_at' => time() + 60,
            ], JSON_THROW_ON_ERROR),
        ];
    },
    'sk-test-server-only',
);
$result = $service->createClientSecret();

assert_test('returns safe client secret', $result['client_secret'] === 'ek_test_secret');
assert_test('uses gpt-realtime', $captured['payload']['session']['model'] === 'gpt-realtime');
assert_test('requests text output', $captured['payload']['session']['output_modalities'] === ['text']);
assert_test('requests PCM24 input', $captured['payload']['session']['audio']['input']['format']['rate'] === 24000);
assert_test('enables automatic tool choice', $captured['payload']['session']['tool_choice'] === 'auto');
assert_test(
    'declares all FAnn tools',
    array_column($captured['payload']['session']['tools'], 'name') === [
        'list_customer_profiles',
        'search_products',
        'get_product',
    ],
);
assert_test('does not return server API key', !str_contains(json_encode($result), 'sk-test'));

section('OpenAI Realtime failures');
$missingKeyThrown = false;
try {
    (new OpenAiRealtimeService(static fn(): array => [], ''))->createClientSecret();
} catch (OpenAiConfigurationException) {
    $missingKeyThrown = true;
}
assert_test('missing server key is rejected', $missingKeyThrown);

$invalidResponseThrown = false;
try {
    $invalid = new OpenAiRealtimeService(
        static fn(): array => ['status' => 200, 'body' => '{}'],
        'sk-test',
    );
    $invalid->createClientSecret();
} catch (OpenAiUpstreamException) {
    $invalidResponseThrown = true;
}
assert_test('invalid upstream response is rejected', $invalidResponseThrown);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
