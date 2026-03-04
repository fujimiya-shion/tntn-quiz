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
            'quiz_option_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'player_token.required' => 'player_token is required.',
            'player_token.uuid' => 'player_token must be a valid UUID.',
            'quiz_option_id.required' => 'quiz_option_id is required.',
        ];
    }
}
