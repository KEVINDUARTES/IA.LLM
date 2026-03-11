<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_offer_id'    => ['required', 'integer', 'exists:job_offers,id'],
            'candidate_cv_id' => ['required', 'integer', 'exists:candidate_cvs,id'],
        ];
    }
}
