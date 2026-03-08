<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveRoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'player_token' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'player_token.required' => 'player_token is required.',
            'player_token.uuid' => 'player_token must be a valid UUID.',
        ];
    }
}
