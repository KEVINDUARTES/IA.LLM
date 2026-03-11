<?php

namespace App\Services;

use App\Enums\ProcessingStatus;
use App\Jobs\ScoreCandidateJob;
use App\Models\CandidateCV;
use App\Models\JobOffer;
use App\Models\ScoringResult;

class ScoreOrchestrationService
{
    /**
     * Find or create a ScoringResult record for the given pair,
     * then dispatch the scoring job unless it is already done or in flight.
     */
    public function initiate(JobOffer $jobOffer, CandidateCV $cv): ScoringResult
    {
        /** @var ScoringResult $result */
        $result = ScoringResult::firstOrCreate(
            ['job_offer_id' => $jobOffer->id, 'candidate_cv_id' => $cv->id],
            ['status' => ProcessingStatus::PENDING],
        );

        if ($result->isCompleted()) {
            return $result;
        }

        // Dispatch a new job only when not already queued/running
        if ($result->status !== ProcessingStatus::PROCESSING) {
            $result->update(['status' => ProcessingStatus::PENDING]);
            ScoreCandidateJob::dispatch($result);
        }

        return $result;
    }
}
