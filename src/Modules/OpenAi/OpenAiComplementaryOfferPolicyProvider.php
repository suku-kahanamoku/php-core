<?php

declare(strict_types=1);

namespace App\Modules\OpenAi;

/**
 * Sestavuje sdílené instrukce doplňkových nabídek z verzovaného souboru pravidel.
 *
 * PHP validuje pouze formát konfigurace a připravuje prompt. Nevyhodnocuje
 * rozhovor, nematchuje produkty, neskóruje kandidáty a nečte katalog ani síť.
 */
final class OpenAiComplementaryOfferPolicyProvider
{
    private const POLICY_FILE = __DIR__ . '/config/complementary-offers.json';
    private array $policy;

    /**
     * Načte výchozí pravidla, nebo přijme explicitně injektovanou konfiguraci.
     *
     * @param array<string, mixed>|null $policy Volitelná konfigurace pro testy či composition root.
     * @throws OpenAiConfigurationException Pokud pravidla chybí nebo mají neplatný formát.
     */
    public function __construct(?array $policy = null)
    {
        if ($policy === null) {
            $json = @file_get_contents(self::POLICY_FILE);
            if ($json === false) {
                throw new OpenAiConfigurationException('Complementary offer policy is missing.');
            }
            try {
                $policy = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw new OpenAiConfigurationException('Complementary offer policy is invalid JSON.', 0, $error);
            }
        }
        if (!is_array($policy)) {
            throw new OpenAiConfigurationException('Complementary offer policy must be an object.');
        }
        $this->validate($policy);
        $this->policy = $policy;
    }

    /**
     * Připraví stručnou navigaci Realtime nebo úplné důkazní podmínky Responses.
     *
     * @param string $audience `realtime` pro interpretaci záměru nebo `responses` pro výběr.
     * @return string Anglické instrukce bez konkrétních ID nebo cen produktů.
     */
    public function instructions(string $audience): string
    {
        if (!in_array($audience, ['realtime', 'responses'], true)) {
            throw new \InvalidArgumentException('Unknown complementary policy audience.');
        }
        $header = '# Complementary offer rulebook v' . $this->policy['version'];
        $common = <<<'PROMPT'
Apply this rulebook only to optional complementary offers after a clear purchase decision, never to primary product selection. These are offer policies, not product facts or proof that the customer requested an add-on.
Automatic offers require an applicable rule and an uncovered useful function. If no rule applies, do not invent a general pairing: keep listening or return no_match in optional search mode. Use product function, not a shared brand, popularity, margin or a more expensive price tag, to establish relevance.
Use the compact decision record carried in the active need: policy version, applicable rule ID, primary purchase evidence, uncovered function, relevant customer constraints, required catalog evidence, additional/basket budget and offer phase. This is factual state, not a transcript, chain-of-thought or sales text. Keep the confirmed primary checklist unchanged.
Customer corrections, explicit refusals, already-owned/committed items and hard constraints override every pairing. Offer one complement only, do not chain automatic offers, and preserve the primary purchase if the add-on is declined. Never infer a medical condition, treatment need or ingredient synergy.
PROMPT;
        if ($audience === 'realtime') {
            $rules = array_map(static fn(array $rule): array => array_intersect_key($rule, array_flip([
                'id', 'primary_function', 'complementary_function', 'when',
            ])), $this->policy['rules']);
            $detail = 'Choose the most specific applicable functional rule. Maintain the compact decision record in continue_listening.active_need and recommend_product.query, including the rule ID, known primary attributes and constraints; state unknown attributes as unknown. Do not invent compatibility. Responses receives the full evidence conditions and makes the final choice.';
        } else {
            $rules = $this->policy['rules'];
            $detail = <<<'PROMPT'
Independently verify the applicable rule and its when/evidence/skip_when conditions; a rule ID supplied in active_need is not proof. Select only a candidate whose function and every mandatory compatibility condition are supported by file_search evidence. Missing evidence is unknown, never a satisfied condition; run a targeted follow-up search before giving up, within the existing search limit. Return only the existing structured decision fields, not this decision record or explanations outside the allowed reason.
Decision order: valid purchase and uncovered function; documented functional compatibility; no customer exclusion or duplicate; in-stock availability; explicit additional/basket budget; accepted optional preferences. Missing an essential compatibility condition is no_match, not a nearest fallback. Unknown additional budget is not unlimited spending; among equally suitable candidates prefer a lower verified price, without guessing income or copying the primary budget. A same-brand or manufacturer pairing is supporting evidence only and cannot override customer requirements.
PROMPT;
        }
        return $header . "\n" . $common . "\n" . $detail . "\nRules: " . json_encode(
            $rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /** Ověří formát a velikost pravidel; jejich význam posuzuje výhradně model. */
    private function validate(array $policy): void
    {
        if (array_diff(array_keys($policy), ['version', 'rules']) !== []
            || !is_int($policy['version'] ?? null) || $policy['version'] < 1
            || !is_array($policy['rules'] ?? null) || !array_is_list($policy['rules'])
            || count($policy['rules']) > 32) {
            throw new OpenAiConfigurationException('Invalid complementary offer policy structure.');
        }
        $ids = [];
        foreach ($policy['rules'] as $rule) {
            if (!is_array($rule) || array_diff(array_keys($rule), ['id', 'primary_function', 'complementary_function', 'when', 'evidence', 'skip_when']) !== []
                || !is_string($rule['id'] ?? null)
                || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $rule['id']) || isset($ids[$rule['id']])) {
                throw new OpenAiConfigurationException('Invalid or duplicate complementary rule ID.');
            }
            $ids[$rule['id']] = true;
            foreach (['primary_function', 'complementary_function'] as $field) {
                if (!is_string($rule[$field] ?? null) || trim($rule[$field]) === '' || mb_strlen($rule[$field]) > 500) {
                    throw new OpenAiConfigurationException('Invalid complementary rule function.');
                }
            }
            foreach (['when', 'evidence', 'skip_when'] as $field) {
                if (!is_array($rule[$field] ?? null) || !array_is_list($rule[$field])
                    || $rule[$field] === [] || count($rule[$field]) > 10) {
                    throw new OpenAiConfigurationException('Invalid complementary rule conditions.');
                }
                foreach ($rule[$field] as $condition) {
                    if (!is_string($condition) || trim($condition) === '' || mb_strlen($condition) > 500) {
                        throw new OpenAiConfigurationException('Invalid complementary rule condition.');
                    }
                }
            }
        }
        if (strlen(json_encode($policy, JSON_THROW_ON_ERROR)) > 24000) {
            throw new OpenAiConfigurationException('Complementary offer policy is too large.');
        }
    }
}
