<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'type' => ['required', 'string', 'in:email,phone'],
            'otp' => ['required', 'string', 'size:6'],
            'purpose' => ['required', 'string', 'in:LOGIN,PASSWORD_RESET'],
        ];
    }
}
