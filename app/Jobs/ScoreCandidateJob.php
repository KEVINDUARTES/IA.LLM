<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\ScoringResult;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScoreCandidateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Scoring itself is deterministic (no AI), so fewer retries are needed.
     * Retries here cover transient DB connection failures.
     */
    public int   $tries   = 3;
    public array $backoff = [10, 30];
    public int   $timeout = 60;

    public function __construct(public readonly ScoringResult $scoringResult) {}

    public function handle(ScoringService $service): void
    {
        // Guard: abort if scoring was already completed (e.g. duplicate dispatch)
        if ($this->scoringResult->isCompleted()) {
            return;
        }

        $this->scoringResult->update(['status' => ProcessingStatus::PROCESSING]);

        // Load relationships to avoid N+1 inside the scoring service
        $jobOffer = $this->scoringResult->jobOffer()->with('criteria')->first();
        $cv       = $this->scoringResult->candidateCv;

        // Prerequisites check — both must be ready before scoring
        if (!$jobOffer->hasCriteriaReady()) {
            Log::warning('ScoreCandidateJob: criteria not ready, releasing back to queue', [
                'scoring_result_id' => $this->scoringResult->id,
                'job_offer_id'      => $jobOffer->id,
            ]);
            // Release back to the queue with a 60-second delay to wait for criteria
            $this->release(60);
            return;
        }

        if (!$cv->isExtractionReady()) {
            Log::warning('ScoreCandidateJob: CV extraction not ready, releasing back to queue', [
                'scoring_result_id' => $this->scoringResult->id,
                'cv_id'             => $cv->id,
            ]);
            $this->release(60);
            return;
        }

        try {
            $result = $service->score($jobOffer, $cv);

            $this->scoringResult->update([
                'score'     => $result['score'],
                'breakdown' => $result['breakdown'],
                'gaps'      => $result['gaps'],
                'status'    => ProcessingStatus::COMPLETED,
            ]);

            Log::info('Scoring completed', [
                'scoring_result_id' => $this->scoringResult->id,
                'score'             => $result['score'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Unexpected error in ScoreCandidateJob', [
                'scoring_result_id' => $this->scoringResult->id,
                'message'           => $e->getMessage(),
            ]);

            $this->markFailed($e->getMessage());
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    private function markFailed(string $reason): void
    {
        $this->scoringResult->update([
            'status'        => ProcessingStatus::FAILED,
            'error_message' => $reason,
        ]);
    }
}
