<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteContactDiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contacts' => ['required', 'array', 'max:10'],
            'contacts.*.type' => [
                'required',
                'string',
                Rule::in(['email', 'phone', 'contact_page']),
            ],
            'contacts.*.value' => ['required', 'string', 'max:2048'],
            'contacts.*.label' => ['nullable', 'string', 'max:160'],
            'contacts.*.source_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'array', 'max:20'],
            'notes.*' => ['string', 'max:500'],
        ];
    }
}
