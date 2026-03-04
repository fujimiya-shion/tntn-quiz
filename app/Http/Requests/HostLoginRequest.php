<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HostLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'email is required.',
            'email.exists' => 'Account does not exist.',
            'password.required' => 'password is required.',
        ];
    }
}
