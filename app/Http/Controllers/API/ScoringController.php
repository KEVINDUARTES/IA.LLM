<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScoringRequest;
use App\Http\Resources\ScoringResultResource;
use App\Models\CandidateCV;
use App\Models\JobOffer;
use App\Models\ScoringResult;
use App\Services\ScoreOrchestrationService;
use Illuminate\Http\JsonResponse;

class ScoringController extends Controller
{
    public function __construct(
        private readonly ScoreOrchestrationService $orchestration,
    ) {}

    /**
     * Initiate or retrieve scoring for a (job_offer, cv) pair.
     *
     * POST /api/score
     *
     * Returns 202 when the scoring is enqueued/in-progress,
     * or 200 when the result is already available.
     */
    public function store(StoreScoringRequest $request): JsonResponse
    {
        $jobOffer = JobOffer::findOrFail($request->validated('job_offer_id'));
        $cv       = CandidateCV::findOrFail($request->validated('candidate_cv_id'));

        $result = $this->orchestration->initiate($jobOffer, $cv);

        $statusCode = $result->isCompleted() ? 200 : 202;

        return (new ScoringResultResource($result))
            ->response()
            ->setStatusCode($statusCode);
    }

    /**
     * Retrieve a scoring result by ID.
     *
     * GET /api/score/{scoringResult}
     */
    public function show(ScoringResult $scoringResult): JsonResponse
    {
        return (new ScoringResultResource($scoringResult))->response();
    }
}
