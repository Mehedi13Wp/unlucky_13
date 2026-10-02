<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOutreachDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer'],
            'channel' => ['required', 'string', Rule::in(['email', 'contact_form'])],
            'recipient' => ['required', 'string', 'max:2048'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'personalization_notes' => ['nullable', 'array', 'max:20'],
            'personalization_notes.*' => ['string', 'max:500'],
        ];
    }
}
