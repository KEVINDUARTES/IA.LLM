<?php

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Models\CandidateCV;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScoringResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_offer_id'    => JobOffer::factory(),
            'candidate_cv_id' => CandidateCV::factory(),
            'score'           => null,
            'breakdown'       => null,
            'gaps'            => null,
            'status'          => ProcessingStatus::PENDING,
            'error_message'   => null,
        ];
    }
}
