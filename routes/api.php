<?php

use App\Http\Controllers\API\CandidateCVController;
use App\Http\Controllers\API\JobOfferController;
use App\Http\Controllers\API\ScoringController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CV Scoring API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Job Offers
    Route::post('/jobs',              [JobOfferController::class, 'store']);
    Route::get('/jobs/{jobOffer}',    [JobOfferController::class, 'show']);

    // Candidate CVs
    Route::post('/cvs',               [CandidateCVController::class, 'store']);
    Route::get('/cvs/{candidateCv}',  [CandidateCVController::class, 'show']);

    // Scoring
    Route::post('/score',                      [ScoringController::class, 'store']);
    Route::get('/score/{scoringResult}',       [ScoringController::class, 'show']);
});
