<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'service_offer' => ['required', 'string', 'min:20', 'max:3000'],
            'target_industry' => ['nullable', 'string', 'max:100'],
            'target_countries' => ['nullable', 'string', 'max:2000'],
            'target_cities' => ['nullable', 'string', 'max:2000'],
            'minimum_score' => ['required', 'integer', 'min:0', 'max:100'],
            'requires_website' => ['required', 'boolean'],
            'requires_public_contact' => ['required', 'boolean'],
            'daily_outreach_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'review_required' => ['required', 'boolean'],
        ];
    }
}
