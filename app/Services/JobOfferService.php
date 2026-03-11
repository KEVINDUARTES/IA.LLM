<?php

namespace App\Services;

use App\Enums\ProcessingStatus;
use App\Jobs\GenerateCriteriaJob;
use App\Models\JobOffer;

class JobOfferService
{
    /**
     * Create a new job offer and immediately dispatch the background job
     * that will call the AI to generate structured criteria.
     */
    public function create(string $title, string $description): JobOffer
    {
        $jobOffer = JobOffer::create([
            'title'           => $title,
            'description'     => $description,
            'criteria_status' => ProcessingStatus::PENDING,
        ]);

        GenerateCriteriaJob::dispatch($jobOffer);

        return $jobOffer;
    }
}
