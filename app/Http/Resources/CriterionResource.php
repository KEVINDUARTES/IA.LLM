<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CriterionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'key'            => $this->key,
            'label'          => $this->label,
            'type'           => $this->type,
            'required'       => $this->required,
            'priority'       => $this->priority,
            'expected_value' => $this->expected_value,
            'weight'         => $this->weight,
        ];
    }
}
