<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HostFinishQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host_token' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'host_token.required' => 'host_token is required.',
            'host_token.uuid' => 'host_token must be a valid UUID.',
        ];
    }
}
