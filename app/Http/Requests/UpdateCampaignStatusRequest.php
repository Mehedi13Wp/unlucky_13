<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    CampaignStatus::Draft->value,
                    CampaignStatus::Active->value,
                    CampaignStatus::Paused->value,
                    CampaignStatus::Completed->value,
                ]),
            ],
        ];
    }
}
