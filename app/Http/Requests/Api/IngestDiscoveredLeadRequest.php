<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IngestDiscoveredLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:180'],
            'website_url' => ['required', 'string', 'max:2048'],
            'industry' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:80'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'observed_signals' => ['nullable', 'array', 'max:25'],
            'observed_signals.*' => ['string', 'max:500'],
        ];
    }
}
