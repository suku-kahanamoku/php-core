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
assert_test('requires every response to call a tool', $captured['payload']['session']['tool_choice'] === 'required');
assert_test('allows enough output tokens for structured tool arguments', $captured['payload']['session']['max_output_tokens'] === 512);
assert_test(
    'stabilizes server VAD for continuous dialogue',
    $captured['payload']['session']['audio']['input']['turn_detection']['silence_duration_ms'] === 700
        && $captured['payload']['session']['audio']['input']['turn_detection']['create_response'] === false
        && $captured['payload']['session']['audio']['input']['turn_detection']['interrupt_response'] === false,
);
assert_test(
    'declares product selection and silent listening tools',
    array_column($captured['payload']['session']['tools'], 'name') === [
        'retrieve_products',
        'get_product',
        'continue_listening',
    ],
);
assert_test(
    'requires category and price evidence for every catalog search',
    $captured['payload']['session']['tools'][0]['parameters']['required'] === [
        'query',
        'category',
        'price_intent',
    ],
);
assert_test(
    'declares explicit recommendation gate evidence',
    array_keys($captured['payload']['session']['tools'][0]['parameters']['properties']) === [
        'query',
        'category',
        'price_intent',
        'limit',
    ],
);
assert_test(
    'lets OpenAI choose enough retrieved alternatives',
    $captured['payload']['session']['tools'][0]['parameters']['properties']['limit']['maximum'] === 20,
);
assert_test(
    'loads only the product ID selected by OpenAI',
    $captured['payload']['session']['tools'][1]['parameters']['required'] === ['product_id']
        && array_keys($captured['payload']['session']['tools'][1]['parameters']['properties']) === ['product_id'],
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
    'waits for category and price or trusted profile before retrieval',
    str_contains($captured['payload']['session']['instructions'], '# Mandatory recommendation gate')
        && str_contains($captured['payload']['session']['instructions'], 'both gate conditions are satisfied')
        && str_contains($captured['payload']['session']['instructions'], 'Gift is an intent or occasion')
        && str_contains($captured['payload']['session']['instructions'], 'can only be satisfied by confirmed price intent'),
);
assert_test(
    'assigns eligibility and ranking exclusively to OpenAI',
    str_contains($captured['payload']['session']['instructions'], 'You alone decide eligibility and ranking')
        && str_contains($captured['payload']['session']['instructions'], 'PHP never evaluates conversation requirements'),
);
assert_test(
    'allows a deliberate return to a previously displayed product',
    str_contains($captured['payload']['session']['instructions'], 'never as a permanent exclusion list')
        && str_contains($captured['payload']['session']['instructions'], 'permit a deliberate later return'),
);
assert_test(
    'preserves active need while replacing a rejected product',
    str_contains($captured['payload']['session']['instructions'], 'Preserve every still-valid fact and constraint')
        && str_contains($captured['payload']['session']['instructions'], 'better evidence-based match')
        && str_contains($captured['payload']['session']['instructions'], 'další produkt'),
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
