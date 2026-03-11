<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'criteria_status' => $this->criteria_status,
            'criteria_count'  => $this->whenLoaded('criteria', fn() => $this->criteria->count()),
            'criteria'        => CriterionResource::collection($this->whenLoaded('criteria')),
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
