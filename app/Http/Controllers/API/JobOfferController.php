<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobOfferRequest;
use App\Http\Resources\JobOfferResource;
use App\Models\JobOffer;
use App\Services\JobOfferService;
use Illuminate\Http\JsonResponse;

class JobOfferController extends Controller
{
    public function __construct(private readonly JobOfferService $jobOfferService) {}

    /**
     * Create a job offer and enqueue criteria generation.
     *
     * POST /api/jobs
     */
    public function store(StoreJobOfferRequest $request): JsonResponse
    {
        $jobOffer = $this->jobOfferService->create(
            title:       $request->validated('title'),
            description: $request->validated('description'),
        );

        return (new JobOfferResource($jobOffer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retrieve a job offer with its generated criteria.
     *
     * GET /api/jobs/{job}
     */
    public function show(JobOffer $jobOffer): JsonResponse
    {
        $jobOffer->load('criteria');

        return (new JobOfferResource($jobOffer))->response();
    }

}
