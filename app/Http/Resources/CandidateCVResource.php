<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateCVResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'cv_hash'           => $this->cv_hash,
            'extraction_status' => $this->extraction_status,
            // Expose structured_data only when extraction is done
            'structured_data'   => $this->when(
                $this->extraction_status->value === 'completed',
                $this->structured_data,
            ),
            'created_at'        => $this->created_at->toIso8601String(),
        ];
    }
}
