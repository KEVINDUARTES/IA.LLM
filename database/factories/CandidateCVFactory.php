<?php

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Models\CandidateCV;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateCVFactory extends Factory
{
    protected $model = CandidateCV::class;

    public function definition(): array
    {
        $cvText = $this->faker->paragraphs(5, true);

        return [
            'cv_text'          => $cvText,
            'cv_hash'          => CandidateCV::computeHash($cvText),
            'structured_data'  => null,
            'extraction_status' => ProcessingStatus::COMPLETED,
        ];
    }
}
