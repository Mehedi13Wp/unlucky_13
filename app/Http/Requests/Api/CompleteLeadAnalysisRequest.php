<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompleteLeadAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qualification_score' => ['required', 'integer', 'between:0,100'],
            'qualification_reason' => ['required', 'string', 'max:2000'],
            'observed_signals' => ['nullable', 'array', 'max:25'],
            'observed_signals.*' => ['string', 'max:500'],
        ];
    }
}
