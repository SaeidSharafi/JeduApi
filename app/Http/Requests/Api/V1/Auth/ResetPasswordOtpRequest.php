<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'otp_code' => ['required', 'string', 'size:4'],
            'tracking_code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'identifier' => [
                'description' => 'The identifier of the user. It can be either phone number or email.',
                'required' => true,
                'example' => '09351234567',
            ],
            'otp_code' => [
                'description' => 'The OTP code received by the user.',
                'required' => true,
                'example' => '1234',
            ],
            'tracking_code' => [
                'description' => 'The tracking code of the OTP request.',
                'required' => true,
                'example' => 'f27873b9-23c3-49be-8667-90afd60bd6b9',
            ],
            'password' => [
                'description' => 'The new password for the user.',
                'required' => true,
                'example' => '12345678',
            ],
            'password_confirmation' => [
                'description' => 'The new password confirmation for the user.',
                'required' => true,
                'example' => '12345678',
            ],
        ];
    }
}
