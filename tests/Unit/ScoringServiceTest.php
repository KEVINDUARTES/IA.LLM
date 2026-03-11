<?php

namespace Tests\Unit;

use App\Enums\CriterionPriority;
use App\Enums\CriterionType;
use App\Enums\MatchResult;
use App\Models\CandidateCV;
use App\Models\Criterion;
use App\Models\JobOffer;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScoringService();
    }

    public function test_years_criterion_full_match(): void
    {
        [$jobOffer, $cv] = $this->createPair(
            criterionData: ['type' => CriterionType::YEARS, 'expected_value' => ['min' => 3], 'weight' => 30],
            cvData: ['laravel_experience_years' => 5],
        );

        $result = $this->service->score($jobOffer, $cv);

        $this->assertEquals(100, $result['score']);
        $this->assertEquals(MatchResult::MATCH->value, $result['breakdown'][0]['result']);
        $this->assertEquals(30, $result['breakdown'][0]['points']);
    }

    public function test_years_criterion_partial_match(): void
    {
        [$jobOffer, $cv] = $this->createPair(
            criterionData: ['type' => CriterionType::YEARS, 'expected_value' => ['min' => 4], 'weight' => 30],
            cvData: ['laravel_experience_years' => 2],
        );

        $result = $this->service->score($jobOffer, $cv);

        // 2/4 = 50% of weight = 15 points → score = 15/30 * 100 = 50
        $this->assertEquals(50, $result['score']);
        $this->assertEquals(MatchResult::NO_MATCH->value, $result['breakdown'][0]['result']);
    }

    public function test_boolean_criterion_match(): void
    {
        [$jobOffer, $cv] = $this->createPair(
            criterionData: ['type' => CriterionType::BOOLEAN, 'expected_value' => ['value' => true], 'weight' => 20],
            cvData: ['has_remote_experience' => true],
        );

        $result = $this->service->score($jobOffer, $cv);

        $this->assertEquals(100, $result['score']);
        $this->assertEquals(MatchResult::MATCH->value, $result['breakdown'][0]['result']);
    }

    public function test_enum_criterion_language_level_match(): void
    {
        [$jobOffer, $cv] = $this->createPair(
            criterionData: ['type' => CriterionType::ENUM, 'expected_value' => ['level' => 'b2'], 'weight' => 20],
            cvData: ['english_level' => 'c1'],
        );

        $result = $this->service->score($jobOffer, $cv);

        $this->assertEquals(MatchResult::MATCH->value, $result['breakdown'][0]['result']);
    }

    public function test_unknown_result_when_key_not_in_cv(): void
    {
        [$jobOffer, $cv] = $this->createPair(
            criterionData: ['type' => CriterionType::YEARS, 'expected_value' => ['min' => 3], 'weight' => 30],
            cvData: [], // empty — key not present
        );

        $result = $this->service->score($jobOffer, $cv);

        $this->assertEquals(0, $result['score']);
        $this->assertEquals(MatchResult::UNKNOWN->value, $result['breakdown'][0]['result']);
    }

    public function test_gaps_contains_only_no_match_criteria(): void
    {
        $jobOffer = JobOffer::factory()->create();
        $jobOffer->criteria()->createMany([
            ['key' => 'laravel_experience_years', 'label' => 'Laravel', 'type' => CriterionType::YEARS, 'required' => true,  'priority' => CriterionPriority::HIGH,   'expected_value' => ['min' => 3], 'weight' => 30],
            ['key' => 'has_remote_experience',    'label' => 'Remote',  'type' => CriterionType::BOOLEAN, 'required' => false, 'priority' => CriterionPriority::LOW, 'expected_value' => ['value' => true], 'weight' => 10],
        ]);

        $cv = CandidateCV::factory()->create([
            'structured_data' => [
                'laravel_experience_years' => 5,
                'has_remote_experience'    => false,
            ],
        ]);

        $result = $this->service->score($jobOffer, $cv);

        $this->assertCount(1, $result['gaps']);
        $this->assertEquals('has_remote_experience', $result['gaps'][0]['key']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createPair(array $criterionData, array $cvData): array
    {
        $jobOffer = JobOffer::factory()->create();
        $jobOffer->criteria()->create(array_merge([
            'key'      => 'laravel_experience_years',
            'label'    => 'Laravel Experience',
            'required' => true,
            'priority' => CriterionPriority::HIGH,
        ], $criterionData));

        $cv = CandidateCV::factory()->create(['structured_data' => $cvData]);

        return [$jobOffer, $cv];
    }
}
