<?php

namespace App\Services;

use App\Models\CandidateCV;
use App\Services\AI\AIServiceInterface;
use Illuminate\Support\Facades\Log;

class CVExtractionService
{
    public function __construct(private readonly AIServiceInterface $ai) {}

    /**
     * Use AI to extract structured data from CV text and persist it.
     * The extraction is job-agnostic: it captures all information that
     * could ever be relevant so the result can be reused across job offers.
     */
    public function extractAndPersist(CandidateCV $cv): void
    {
        $structured = $this->ai->structuredCompletion(
            systemPrompt: $this->buildSystemPrompt(),
            userPrompt:   $this->buildUserPrompt($cv->cv_text),
        );

        if (empty($structured)) {
            Log::error('AI returned empty structured data for CV', ['cv_id' => $cv->id]);
            throw new \RuntimeException('AI returned empty structured data for CV.');
        }

        $cv->update([
            'structured_data' => $structured,
        ]);
    }

    // -------------------------------------------------------------------------
    // Prompt builders
    // -------------------------------------------------------------------------

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert CV/resume parser.
Your task is to extract ALL relevant professional information from the CV text
and return it as a single, flat-to-moderately-nested JSON object.

The JSON keys you produce will be matched against job criteria keys, so follow
these naming conventions strictly:

YEARS OF EXPERIENCE — use pattern: <technology>_experience_years (integer)
  Examples: laravel_experience_years, php_experience_years, react_experience_years,
            nodejs_experience_years, python_experience_years, docker_experience_years,
            aws_experience_years, mysql_experience_years, postgresql_experience_years,
            redis_experience_years, kubernetes_experience_years

BOOLEAN FLAGS — use pattern: has_<skill> (boolean)
  Examples: has_remote_experience, has_leadership_experience, has_agile_experience,
            has_tdd_experience, has_microservices_experience, has_team_lead_experience,
            has_api_design_experience

LANGUAGE LEVELS — use pattern: <language>_level (CEFR string: A1,A2,B1,B2,C1,C2,native)
  Examples: english_level, spanish_level, portuguese_level

NUMERIC SCORES — use pattern: <skill>_score (integer 1-5)
  Examples: communication_score, problem_solving_score

OTHER FIELDS (always include):
  total_years_experience   (integer)
  current_role             (string)
  education_level          (bachelor|master|phd|associate|none)
  education_field          (string or null)

RULES:
- Only include fields for which there is actual evidence in the CV.
- For years of experience, sum across all positions if the technology appears multiple times.
- If a language level is not explicitly stated, infer from context (e.g. "working proficiency" ≈ B2).
- Return ONLY the JSON object, no extra keys, no explanations.
PROMPT;
    }

    private function buildUserPrompt(string $cvText): string
    {
        return "CV text:\n\n{$cvText}";
    }
}
