<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quiz_id' => ['required', 'integer', 'exists:quizzes,id'],
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'host_gender' => ['nullable', 'in:male,female'],
            'host_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'quiz_id.required' => 'quiz_id is required.',
            'quiz_id.exists' => 'Quiz does not exist.',
            'email.required' => 'email is required.',
            'email.exists' => 'Account does not exist.',
            'password.required' => 'password is required.',
            'host_gender.in' => 'host_gender must be male or female.',
        ];
    }
}
