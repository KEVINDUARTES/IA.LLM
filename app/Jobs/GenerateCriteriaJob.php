<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Exceptions\AIProviderException;
use App\Models\JobOffer;
use App\Services\CriteriaGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCriteriaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is marked as failed.
     * Retries are most useful for transient AI provider errors (429, 5xx).
     */
    public int $tries = 3;

    /**
     * Exponential backoff in seconds between retries.
     */
    public array $backoff = [30, 90, 300];

    /**
     * Time (seconds) to allow the job to run before timing out.
     */
    public int $timeout = 120;

    public function __construct(public readonly JobOffer $jobOffer) {}

    public function handle(CriteriaGenerationService $service): void
    {
        $this->jobOffer->update(['criteria_status' => ProcessingStatus::PROCESSING]);

        try {
            $service->generateAndPersist($this->jobOffer);
            $this->jobOffer->update(['criteria_status' => ProcessingStatus::COMPLETED]);

            Log::info('Criteria generated successfully', [
                'job_offer_id' => $this->jobOffer->id,
                'count'        => $this->jobOffer->criteria()->count(),
            ]);
        } catch (AIProviderException $e) {
            Log::warning('AI provider error in GenerateCriteriaJob', [
                'job_offer_id' => $this->jobOffer->id,
                'status_code'  => $e->getStatusCode(),
                'message'      => $e->getMessage(),
                'attempt'      => $this->attempts(),
            ]);

            // Only retry for transient errors; fail immediately for auth/bad request
            if (!$e->isRetryable()) {
                $this->markFailed($e->getMessage());
                $this->fail($e);
                return;
            }

            throw $e; // Let the queue retry with backoff
        } catch (\Throwable $e) {
            Log::error('Unexpected error in GenerateCriteriaJob', [
                'job_offer_id' => $this->jobOffer->id,
                'message'      => $e->getMessage(),
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
        $this->jobOffer->update(['criteria_status' => ProcessingStatus::FAILED]);

        Log::error('GenerateCriteriaJob permanently failed', [
            'job_offer_id' => $this->jobOffer->id,
            'reason'       => $reason,
        ]);
    }
}
