<?php

namespace App\Services;

use App\Enums\CriterionType;
use App\Enums\MatchResult;
use App\Models\CandidateCV;
use App\Models\Criterion;
use App\Models\JobOffer;

class ScoringService
{
    /**
     * Levels used for ENUM (e.g. CEFR language) ordering.
     * A candidate with a higher index meets or exceeds a required lower index.
     */
    private const ENUM_LEVEL_ORDER = [
        'a1', 'a2', 'b1', 'b2', 'c1', 'c2', 'native',
        // Education
        'none', 'associate', 'bachelor', 'master', 'phd',
    ];

    /**
     * Execute the full deterministic scoring of a CV against a job offer.
     *
     * @return array{score: int, breakdown: array, gaps: array}
     */
    public function score(JobOffer $jobOffer, CandidateCV $cv): array
    {
        // Eager-load to avoid N+1
        $criteria = $jobOffer->criteria()->get();
        $cvData   = $cv->structured_data ?? [];

        $breakdown    = [];
        $totalWeight  = 0;
        $earnedPoints = 0;

        foreach ($criteria as $criterion) {
            $entry         = $this->evaluateCriterion($criterion, $cvData);
            $breakdown[]   = $entry;
            $totalWeight  += $criterion->weight;
            $earnedPoints += $entry['points'];
        }

        $score = $totalWeight > 0
            ? (int) round(($earnedPoints / $totalWeight) * 100)
            : 0;

        // Clamp to valid range
        $score = max(0, min(100, $score));

        $gaps = array_values(
            array_filter($breakdown, fn(array $r) => $r['result'] === MatchResult::NO_MATCH->value)
        );

        return compact('score', 'breakdown', 'gaps');
    }

    // -------------------------------------------------------------------------
    // Per-criterion evaluation
    // -------------------------------------------------------------------------

    private function evaluateCriterion(Criterion $criterion, array $cvData): array
    {
        $value = $this->resolveValue($criterion->key, $criterion->type, $cvData, $criterion);

        $base = [
            'criterion' => $criterion->label,
            'key'       => $criterion->key,
            'weight'    => $criterion->weight,
            'required'  => $criterion->required,
        ];

        if ($value === null) {
            return array_merge($base, [
                'result'     => MatchResult::UNKNOWN->value,
                'points'     => 0,
                'evidence'   => 'Not found in CV',
                'confidence' => 0.0,
            ]);
        }

        $evaluation = match ($criterion->type) {
            CriterionType::BOOLEAN   => $this->evaluateBoolean($criterion, $value),
            CriterionType::YEARS     => $this->evaluateYears($criterion, $value),
            CriterionType::ENUM      => $this->evaluateEnum($criterion, $value),
            CriterionType::SCORE_1_5 => $this->evaluateScore1to5($criterion, $value),
        };

        return array_merge($base, $evaluation);
    }

    // -------------------------------------------------------------------------
    // Value resolution with fallback strategies
    // -------------------------------------------------------------------------

    /**
     * Technology/concept synonyms: maps criterion key words to CV key words.
     * Used when the criteria-generation AI uses different terminology than
     * the CV extraction AI.
     */
    private const KEY_SYNONYMS = [
        'cloud'          => ['aws', 'gcp', 'azure'],
        'ai'             => ['llm', 'openai', 'gpt'],
        'ai_integration' => ['llm', 'openai'],
        'ml'             => ['ai', 'llm'],
        'k8s'            => ['kubernetes'],
        'postgres'       => ['postgresql'],
        // 'js' intentionally omitted — too generic, causes vue_js → javascript false positives
        'ts'             => ['typescript'],
        'webhook'        => ['api', 'rest'],
    ];

    /**
     * Try to find a value in cvData for the given criterion key.
     * Falls back to common naming variations when the exact key is not present.
     * This bridges the gap when criteria-generation AI uses different key names
     * than the CV extraction AI.
     */
    private function resolveValue(string $key, CriterionType $type, array $cvData, ?Criterion $criterion = null): mixed
    {
        // 1. Exact match
        if (array_key_exists($key, $cvData)) {
            return $cvData[$key];
        }

        // 2. For boolean criteria: check has_{key} and {key}_years > 0
        if ($type === CriterionType::BOOLEAN) {
            $hasKey = 'has_' . $key;
            if (array_key_exists($hasKey, $cvData)) {
                return $cvData[$hasKey];
            }

            $base     = preg_replace('/_experience$/', '', $key);
            $yearsKey = $base . '_experience_years';
            if (array_key_exists($yearsKey, $cvData)) {
                return $cvData[$yearsKey] > 0;
            }

            $yearsKey2 = $key . '_years';
            if (array_key_exists($yearsKey2, $cvData)) {
                return $cvData[$yearsKey2] > 0;
            }
        }

        // 3. For years criteria: check {key}_years
        if ($type === CriterionType::YEARS && !str_ends_with($key, '_years')) {
            $yearsKey = $key . '_years';
            if (array_key_exists($yearsKey, $cvData)) {
                return $cvData[$yearsKey];
            }
        }

        // 4. Synonym-based lookup
        $base      = preg_replace('/_experience(_years)?$/', '', $key);
        $baseParts = explode('_', $base);

        foreach ($baseParts as $part) {
            $synonyms = self::KEY_SYNONYMS[$part] ?? [];
            foreach ($synonyms as $synonym) {
                foreach ($cvData as $cvKey => $cvValue) {
                    if (str_contains($cvKey, $synonym)) {
                        return $this->normalizeFoundValue($cvKey, $cvValue, $type, $criterion ?? null);
                    }
                }
            }
        }

        // 5. Partial key match — only on the FIRST significant part to avoid cross-technology false positives
        $mainWord = $baseParts[0] ?? $key;

        if (strlen($mainWord) >= 3) {
            foreach ($cvData as $cvKey => $cvValue) {
                if (str_contains($cvKey, $mainWord)) {
                    return $this->normalizeFoundValue($cvKey, $cvValue, $type, $criterion ?? null);
                }
            }
        }

        return null;
    }

    /**
     * Normalize a value found via fallback lookup.
     *
     * When the criterion type is ENUM with an `accepted` list and the CV value
     * is numeric (came from a _years key), we try to derive the proper enum
     * value from the matched CV key name so the enum comparison works correctly.
     * e.g. found `aws_experience_years: 6` for `cloud_experience` accepted ["AWS","GCP"]
     *      → returns "AWS" so the enum evaluator can match it against the accepted list.
     */
    private function normalizeFoundValue(
        string $cvKey,
        mixed $cvValue,
        CriterionType $type,
        ?Criterion $criterion,
    ): mixed {
        if ($type === CriterionType::BOOLEAN) {
            return is_numeric($cvValue) ? $cvValue > 0 : (bool) $cvValue;
        }

        if ($type === CriterionType::ENUM && is_numeric($cvValue) && $criterion !== null) {
            $accepted = $criterion->expected_value['accepted'] ?? [];
            foreach ($accepted as $acceptedValue) {
                // Check if the accepted value's first word appears in the matched CV key
                $firstWord = strtolower(explode(' ', $acceptedValue)[0]);
                if (strlen($firstWord) >= 2 && str_contains(strtolower($cvKey), $firstWord)) {
                    return $acceptedValue; // return the proper accepted enum value
                }
            }
        }

        return $cvValue;
    }

    // -------------------------------------------------------------------------
    // Type-specific evaluators
    // -------------------------------------------------------------------------

    private function evaluateBoolean(Criterion $criterion, mixed $value): array
    {
        $expected = (bool) ($criterion->expected_value['value'] ?? true);
        $actual   = (bool) $value;
        $match    = $actual === $expected;

        return [
            'result'     => $match ? MatchResult::MATCH->value : MatchResult::NO_MATCH->value,
            'points'     => $match ? $criterion->weight : 0,
            'evidence'   => $actual ? 'Present in CV' : 'Not mentioned in CV',
            'confidence' => 1.0,
        ];
    }

    private function evaluateYears(Criterion $criterion, mixed $value): array
    {
        $required = (float) ($criterion->expected_value['min'] ?? 0);
        $actual   = (float) $value;

        if ($actual >= $required) {
            return [
                'result'     => MatchResult::MATCH->value,
                'points'     => $criterion->weight,
                'evidence'   => "{$actual} year(s) found (required: {$required}+)",
                'confidence' => 0.9,
            ];
        }

        // Partial credit: proportional to how close the candidate is
        $ratio         = $required > 0 ? min($actual / $required, 1.0) : 0.0;
        $partialPoints = (int) round($criterion->weight * $ratio);

        return [
            'result'     => MatchResult::NO_MATCH->value,
            'points'     => $partialPoints,
            'evidence'   => "{$actual} year(s) found (required: {$required}+)",
            'confidence' => 0.9,
        ];
    }

    private function evaluateEnum(Criterion $criterion, mixed $value): array
    {
        $expected  = strtolower((string) ($criterion->expected_value['level'] ?? ''));
        $actual    = strtolower((string) $value);
        $accepted  = array_map('strtolower', $criterion->expected_value['accepted'] ?? []);

        // If an explicit list of accepted values is provided, use it
        if (!empty($accepted)) {
            $match = in_array($actual, $accepted, strict: true);
        } else {
            // Otherwise use hierarchical ordering (higher index = better)
            $expectedIdx = array_search($expected, self::ENUM_LEVEL_ORDER);
            $actualIdx   = array_search($actual,   self::ENUM_LEVEL_ORDER);

            // Unknown levels treated as unknown
            if ($expectedIdx === false || $actualIdx === false) {
                return [
                    'result'     => MatchResult::UNKNOWN->value,
                    'points'     => 0,
                    'evidence'   => "Level '{$value}' could not be compared to expected '{$expected}'",
                    'confidence' => 0.5,
                ];
            }

            $match = $actualIdx >= $expectedIdx;
        }

        return [
            'result'     => $match ? MatchResult::MATCH->value : MatchResult::NO_MATCH->value,
            'points'     => $match ? $criterion->weight : 0,
            'evidence'   => "Level '{$value}' found (required: '{$expected}')",
            'confidence' => 0.85,
        ];
    }

    private function evaluateScore1to5(Criterion $criterion, mixed $value): array
    {
        $required = (int) ($criterion->expected_value['min'] ?? 3);
        $actual   = (int) $value;
        $actual   = max(1, min(5, $actual)); // clamp to valid range

        if ($actual >= $required) {
            return [
                'result'     => MatchResult::MATCH->value,
                'points'     => $criterion->weight,
                'evidence'   => "Score {$actual}/5 (required: {$required}/5)",
                'confidence' => 0.8,
            ];
        }

        $ratio         = $required > 0 ? ($actual / $required) : 0.0;
        $partialPoints = (int) round($criterion->weight * $ratio);

        return [
            'result'     => MatchResult::NO_MATCH->value,
            'points'     => $partialPoints,
            'evidence'   => "Score {$actual}/5 (required: {$required}/5)",
            'confidence' => 0.8,
        ];
    }
}
