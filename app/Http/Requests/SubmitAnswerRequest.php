<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'player_token' => ['required', 'uuid'],
            'quiz_option_id' => ['nullable', 'integer', 'required_without:answer_text'],
            'answer_text' => ['nullable', 'string', 'max:255', 'required_without:quiz_option_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'player_token.required' => 'player_token is required.',
            'player_token.uuid' => 'player_token must be a valid UUID.',
            'quiz_option_id.required_without' => 'quiz_option_id is required when answer_text is empty.',
            'answer_text.required_without' => 'answer_text is required when quiz_option_id is empty.',
        ];
    }
}
