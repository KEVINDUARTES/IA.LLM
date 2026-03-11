<?php

namespace App\Services;

use App\Enums\ProcessingStatus;
use App\Jobs\ExtractCVDataJob;
use App\Models\CandidateCV;

class CVService
{
    /**
     * Submit a CV for processing.
     *
     * If a CV with the same hash already exists and extraction is complete,
     * the existing record is returned as-is to avoid redundant AI calls.
     * Otherwise a new record is created (or an existing failed/pending one is reset)
     * and the extraction job is dispatched.
     */
    public function submit(string $cvText): CandidateCV
    {
        $hash = CandidateCV::computeHash($cvText);

        $existing = CandidateCV::where('cv_hash', $hash)->first();

        if ($existing) {
            // Reuse completed extractions — the core deduplication guarantee
            if ($existing->isExtractionReady()) {
                return $existing;
            }

            // If a previous attempt failed, reset and retry
            if ($existing->extraction_status === ProcessingStatus::FAILED) {
                $existing->update(['extraction_status' => ProcessingStatus::PENDING]);
                ExtractCVDataJob::dispatch($existing);
            }

            return $existing;
        }

        $cv = CandidateCV::create([
            'cv_text'          => $cvText,
            'cv_hash'          => $hash,
            'extraction_status' => ProcessingStatus::PENDING,
        ]);

        ExtractCVDataJob::dispatch($cv);

        return $cv;
    }
}
