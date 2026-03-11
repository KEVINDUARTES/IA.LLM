<?php

namespace App\Http\Resources;

use App\Enums\ProcessingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScoringResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isCompleted = $this->status === ProcessingStatus::COMPLETED;

        return [
            'id'              => $this->id,
            'status'          => $this->status,
            'job_offer_id'    => $this->job_offer_id,
            'candidate_cv_id' => $this->candidate_cv_id,

            // Scoring data is only meaningful once completed
            'score'     => $this->when($isCompleted, $this->score),
            'breakdown' => $this->when($isCompleted, $this->breakdown),
            'gaps'      => $this->when($isCompleted, $this->gaps),

            'error_message' => $this->when(
                $this->status === ProcessingStatus::FAILED,
                $this->error_message,
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
