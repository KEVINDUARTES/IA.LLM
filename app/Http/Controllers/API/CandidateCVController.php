<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCVRequest;
use App\Http\Resources\CandidateCVResource;
use App\Models\CandidateCV;
use App\Services\CVService;
use Illuminate\Http\JsonResponse;

class CandidateCVController extends Controller
{
    public function __construct(private readonly CVService $cvService) {}

    /**
     * Submit a CV for structured data extraction.
     * Returns the existing record immediately if the same CV was already processed.
     *
     * POST /api/cvs
     */
    public function store(StoreCVRequest $request): JsonResponse
    {
        $cv = $this->cvService->submit($request->validated('cv_text'));

        // 200 = returned an existing record; 201 = newly created
        $statusCode = $cv->wasRecentlyCreated ? 201 : 200;

        return (new CandidateCVResource($cv))
            ->response()
            ->setStatusCode($statusCode);
    }

    /**
     * Retrieve a CV record with its extraction status.
     *
     * GET /api/cvs/{cv}
     */
    public function show(CandidateCV $candidateCv): JsonResponse
    {
        return (new CandidateCVResource($candidateCv))->response();
    }
}
