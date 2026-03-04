<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.required' => 'gender is required.',
            'gender.in' => 'gender must be male or female.',
        ];
    }
}
