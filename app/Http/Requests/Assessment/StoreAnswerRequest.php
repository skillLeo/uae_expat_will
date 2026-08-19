<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The assessment needs no account. Authorisation is the session token,
        // which the controller resolves before this request is reached.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_key' => ['required', 'string', 'max:40'],
            // The shape is validated properly by AnswerValidator against the
            // question's own type and options. Here we only bound the input.
            'value' => ['present'],
            'value.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
