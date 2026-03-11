<?php

namespace App\Services;

use App\Enums\CriterionPriority;
use App\Models\JobOffer;
use App\Services\AI\AIServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CriteriaGenerationService
{
    public function __construct(private readonly AIServiceInterface $ai) {}

    /**
     * Ask the AI to generate structured selection criteria from the job description,
     * then persist them linked to the given offer.
     *
     * Idempotent: if criteria already exist for this offer they are deleted and regenerated
     * (allows re-triggering from the job if needed).
     */
    public function generateAndPersist(JobOffer $jobOffer): void
    {
        $rawCriteria = $this->ai->structuredCompletion(
            systemPrompt: $this->buildSystemPrompt(),
            userPrompt:   $this->buildUserPrompt($jobOffer),
        );

        $criteriaList = $rawCriteria['criteria'] ?? [];

        if (empty($criteriaList)) {
            Log::error('AI returned no criteria for job offer', ['job_offer_id' => $jobOffer->id]);
            throw new \RuntimeException('AI returned an empty criteria list.');
        }

        DB::transaction(function () use ($jobOffer, $criteriaList) {
            // Wipe any previous attempt before re-inserting
            $jobOffer->criteria()->delete();

            foreach ($criteriaList as $raw) {
                $priority = CriterionPriority::tryFrom($raw['priority'] ?? '') ?? CriterionPriority::MEDIUM;

                $jobOffer->criteria()->create([
                    'key'            => $raw['key'],
                    'label'          => $raw['label'],
                    'type'           => $raw['type'],
                    'required'       => (bool) ($raw['required'] ?? false),
                    'priority'       => $priority->value,
                    // Derive weight from priority when AI does not supply one
                    'weight'         => $raw['weight'] ?? $priority->defaultWeight(),
                    'expected_value' => $raw['expected_value'],
                ]);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Prompt builders
    // -------------------------------------------------------------------------

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert technical recruiter and job requirements analyst.
Your task is to extract structured selection criteria from a job description.

Return ONLY a valid JSON object with this exact structure:
{
  "criteria": [
    {
      "key":            "snake_case_identifier",
      "label":          "Human readable label",
      "type":           "boolean|years|enum|score_1_5",
      "required":       true|false,
      "priority":       "high|medium|low",
      "expected_value": { ... },
      "weight":         <integer 1-30>
    }
  ]
}

Rules for each field:
- key: unique, snake_case, descriptive (e.g. laravel_experience_years, english_level, has_remote_experience)
- type meanings and expected_value format:
  - boolean   → expected_value: {"value": true}
  - years     → expected_value: {"min": <number>}
  - enum      → expected_value: {"level": "<value>", "accepted": ["<v1>","<v2>",...]}
  - score_1_5 → expected_value: {"min": <1-5>}
- weight: high priority = 25-30, medium = 15-20, low = 5-10
- Extract 5 to 12 criteria. Focus on what truly differentiates candidates.
- Do NOT include criteria that cannot realistically be inferred from a CV.
PROMPT;
    }

    private function buildUserPrompt(JobOffer $jobOffer): string
    {
        return "Job title: {$jobOffer->title}\n\nJob description:\n{$jobOffer->description}";
    }
}
