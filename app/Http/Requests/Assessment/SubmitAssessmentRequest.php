<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'declarations' => ['array'],
            'declarations.*' => ['integer'],

            // Collected on the review and referral outcomes only.
            'contact' => ['array'],
            'contact.full_name' => ['nullable', 'string', 'max:160'],
            'contact.email' => ['nullable', 'email:rfc', 'max:190'],
            'contact.phone' => ['nullable', 'string', 'max:32'],
            'contact.nationality' => ['nullable', 'string', 'max:100'],
            'contact.country_of_residence' => ['nullable', 'string', 'max:100'],
            'contact.preferred_contact_method' => ['nullable', 'string', 'in:email,phone,whatsapp'],
            'contact.summary' => ['nullable', 'string', 'max:500'],
        ];
    }
}
