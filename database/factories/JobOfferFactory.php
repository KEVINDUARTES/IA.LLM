<?php

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'           => $this->faker->jobTitle(),
            'description'     => $this->faker->paragraphs(3, true),
            'criteria_status' => ProcessingStatus::COMPLETED,
        ];
    }
}
