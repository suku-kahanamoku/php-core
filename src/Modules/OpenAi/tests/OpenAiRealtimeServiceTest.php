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
    'declares only passive product selection tools',
    array_column($captured['payload']['session']['tools'], 'name') === [
        'search_products',
        'get_product',
    ],
);
assert_test(
    'declares separate displayed and rejected product histories',
    isset($captured['payload']['session']['tools'][0]['parameters']['properties']['displayed_product_ids'])
        && isset($captured['payload']['session']['tools'][0]['parameters']['properties']['rejected_product_ids']),
);
assert_test(
    'separates mandatory and preferred product attributes',
    isset($captured['payload']['session']['tools'][0]['parameters']['properties']['required_attributes'])
        && isset($captured['payload']['session']['tools'][0]['parameters']['properties']['preferred_attributes'])
        && isset($captured['payload']['session']['tools'][0]['parameters']['properties']['negative_preferences']),
);
assert_test(
    'declares hard exclusions for rejected product attributes',
    isset($captured['payload']['session']['tools'][0]['parameters']['properties']['excluded_attributes']),
);
assert_test(
    'requires hard constraints again during final product verification',
    $captured['payload']['session']['tools'][1]['parameters']['required']
        === ['product_id', 'required_attributes', 'excluded_attributes'],
);
assert_test(
    'does not offer profile probability as a primary search input',
    !isset($captured['payload']['session']['tools'][0]['parameters']['properties']['profile_id']),
);
assert_test(
    'forbids customer questions and sales arguments',
    str_contains($captured['payload']['session']['instructions'], 'Never ask the customer a question.')
        && str_contains($captured['payload']['session']['instructions'], 'Never produce spoken responses, sales arguments'),
);
assert_test(
    'uses structured English instructions for the Czech conversation',
    str_contains($captured['payload']['session']['instructions'], '# Role and objective')
        && str_contains($captured['payload']['session']['instructions'], 'live Czech conversation'),
);
assert_test(
    'waits silently until conversation evidence supports a search',
    str_contains($captured['payload']['session']['instructions'], 'silently WAIT and keep listening'),
);
assert_test(
    'keeps hard requirements separate from ranking preferences',
    str_contains($captured['payload']['session']['instructions'], 'required_attributes')
        && str_contains($captured['payload']['session']['instructions'], 'preferred_attributes'),
);
assert_test('does not return server API key', !str_contains(json_encode($result), 'sk-test'));

$customModelPayload = null;
(new OpenAiRealtimeService(
    static function (string $apiKey, array $payload) use (&$customModelPayload): array {
        $customModelPayload = $payload;
        return [
            'status' => 200,
            'body' => json_encode([
                'value' => 'ek_custom_model',
                'expires_at' => time() + 60,
            ], JSON_THROW_ON_ERROR),
        ];
    },
    'sk-test',
    'gpt-realtime-custom',
))->createClientSecret();
assert_test(
    'allows the Realtime model to be configured without code changes',
    $customModelPayload['session']['model'] === 'gpt-realtime-custom',
);

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
