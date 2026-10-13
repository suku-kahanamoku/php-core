#!/usr/bin/env php
<?php

declare(strict_types=1);

/** Offline test struktury sdílených instrukcí; nehodnotí skutečné chování modelu. */

use App\Modules\OpenAi\OpenAiComplementaryOfferPolicyProvider;
use App\Modules\OpenAi\OpenAiConfigurationException;

if (!function_exists('assert_test')) {
    require_once __DIR__ . '/../../../../tests/bootstrap.php';
}
if (!isset($runnerMode)) {
    $passed = 0;
    $failed = 0;
}
require_once __DIR__ . '/../../../../vendor/autoload.php';

section('Shared complementary offer policy');
$offerDefinition = json_decode(file_get_contents(__DIR__ . '/../config/complementary-offers.json'), true, flags: JSON_THROW_ON_ERROR);
$offerPolicy = new OpenAiComplementaryOfferPolicyProvider();
$realtimePolicyText = $offerPolicy->instructions('realtime');
$responsesPolicyText = $offerPolicy->instructions('responses');
$realtimeRules = json_decode(explode('Rules: ', $realtimePolicyText, 2)[1], true, flags: JSON_THROW_ON_ERROR);
$responsesRules = json_decode(explode('Rules: ', $responsesPolicyText, 2)[1], true, flags: JSON_THROW_ON_ERROR);
assert_test('uses the same configured relation IDs for both models',
    array_column($realtimeRules, 'id') === array_column($responsesRules, 'id')
        && $responsesRules === $offerDefinition['rules']);
assert_test('keeps realtime navigation smaller than full retrieval evidence',
    !isset($realtimeRules[0]['evidence']) && isset($realtimeRules[0]['when'])
        && isset($responsesRules[0]['evidence'], $responsesRules[0]['skip_when'])
        && strlen($realtimePolicyText) < strlen($responsesPolicyText));
assert_test('has function rules without fixed product IDs, brands or prices',
    count($responsesRules) > 0 && !str_contains($responsesPolicyText, 'product_id":')
        && $responsesRules[0]['id'] === 'eye_makeup_removal');
assert_test('passes mandatory compatibility proof and contextual suppression conditions',
    str_contains($responsesRules[0]['evidence'][1], 'waterproof')
        && str_contains($responsesRules[2]['when'][0], 'UV-cured')
        && str_contains($responsesRules[6]['when'][0], 'expresses interest'));

$customOfferDefinition = ['version' => 2, 'rules' => [[
    'id' => 'custom_function', 'primary_function' => 'Custom primary function',
    'complementary_function' => 'Custom supporting function',
    'when' => ['Confirmed uncovered need.'], 'evidence' => ['Documented compatibility.'],
    'skip_when' => ['Customer refuses.'],
]]];
$customPolicy = new OpenAiComplementaryOfferPolicyProvider($customOfferDefinition);
assert_test('allows injected configuration without modifying model services',
    str_contains($customPolicy->instructions('realtime'), 'rulebook v2')
        && str_contains($customPolicy->instructions('responses'), 'custom_function')
        && !str_contains($customPolicy->instructions('responses'), 'eye_makeup_removal'));
assert_test('empty rulebook disables automatic pairings without a generic fallback',
    str_ends_with((new OpenAiComplementaryOfferPolicyProvider(['version' => 1, 'rules' => []]))->instructions('realtime'), 'Rules: []'));

$badOfferDefinitions = [
    ['version' => '1', 'rules' => []],
    ['version' => 0, 'rules' => []],
    ['version' => 1, 'rules' => ['not-a-list' => []]],
    ['version' => 1, 'rules' => array_fill(0, 33, $customOfferDefinition['rules'][0])],
    ['version' => 1, 'rules' => [$customOfferDefinition['rules'][0], $customOfferDefinition['rules'][0]]],
    ['version' => 1, 'rules' => [array_replace($customOfferDefinition['rules'][0], ['id' => 'bad id'])]],
    ['version' => 1, 'rules' => [array_replace($customOfferDefinition['rules'][0], ['evidence' => []])]],
    ['version' => 1, 'rules' => [array_replace($customOfferDefinition['rules'][0], ['when' => [true]])]],
    ['version' => 1, 'rules' => [array_replace($customOfferDefinition['rules'][0], ['skip_when' => [str_repeat('x', 501)]])]],
    ['version' => 1, 'rules' => [$customOfferDefinition['rules'][0]], 'unexpected' => 'ignored rule'],
    ['version' => 1, 'rules' => [array_replace($customOfferDefinition['rules'][0], ['product_id' => 99])]],
];
foreach ($badOfferDefinitions as $index => $badOfferDefinition) {
    $rejected = false;
    try {
        new OpenAiComplementaryOfferPolicyProvider($badOfferDefinition);
    } catch (OpenAiConfigurationException) {
        $rejected = true;
    }
    assert_test('rejects malformed rulebook case ' . $index, $rejected);
}
$unknownAudienceRejected = false;
try {
    $offerPolicy->instructions('unknown');
} catch (InvalidArgumentException) {
    $unknownAudienceRejected = true;
}
assert_test('does not guess an unsupported prompt role', $unknownAudienceRejected);

if (!isset($runnerMode)) {
    print_results();
    exit($failed > 0 ? 1 : 0);
}
