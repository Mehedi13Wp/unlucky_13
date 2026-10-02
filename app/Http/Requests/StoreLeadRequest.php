<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'campaign_uuid' => ['required', 'uuid'],
            'business_name' => ['required', 'string', 'max:180'],
            'website_url' => ['nullable', 'string', 'max:2048'],
            'industry' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:80'],
            'source_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
