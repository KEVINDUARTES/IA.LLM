<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Exceptions\AIProviderException;
use App\Models\CandidateCV;
use App\Services\CVExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractCVDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public array $backoff = [30, 90, 300];
    public int   $timeout = 120;

    public function __construct(public readonly CandidateCV $cv) {}

    public function handle(CVExtractionService $service): void
    {
        if ($this->cv->isExtractionReady()) {
            Log::info('CV extraction already completed, skipping', ['cv_id' => $this->cv->id]);
            return;
        }

        $this->cv->update(['extraction_status' => ProcessingStatus::PROCESSING]);

        try {
            $service->extractAndPersist($this->cv);
            $this->cv->update(['extraction_status' => ProcessingStatus::COMPLETED]);

            Log::info('CV extraction completed', ['cv_id' => $this->cv->id]);
        } catch (AIProviderException $e) {
            Log::warning('AI provider error in ExtractCVDataJob', [
                'cv_id'       => $this->cv->id,
                'status_code' => $e->getStatusCode(),
                'message'     => $e->getMessage(),
                'attempt'     => $this->attempts(),
            ]);

            if (!$e->isRetryable()) {
                $this->markFailed($e->getMessage());
                $this->fail($e);
                return;
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Unexpected error in ExtractCVDataJob', [
                'cv_id'   => $this->cv->id,
                'message' => $e->getMessage(),
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
        $this->cv->update(['extraction_status' => ProcessingStatus::FAILED]);

        Log::error('ExtractCVDataJob permanently failed', [
            'cv_id'  => $this->cv->id,
            'reason' => $reason,
        ]);
    }
}
