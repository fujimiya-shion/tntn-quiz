<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HostCreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quiz_id' => ['required', 'integer', 'exists:quizzes,id'],
            'host_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'quiz_id.required' => 'quiz_id is required.',
            'quiz_id.exists' => 'Quiz does not exist.',
        ];
    }
}
