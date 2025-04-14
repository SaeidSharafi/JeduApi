<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\OtpType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'otp_code' => ['required', 'string', 'size:4'],
            'tracking_code' => ['required', 'string'],
            'otp_type' => ['required', 'string', Rule::enum(OtpType::class)],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'identifier' => [
                'description' => 'The identifier of the user (phone number or email)',
                'required' => true,
                'example' => '09351234567',
            ],
            'otp_code' => [
                'description' => 'The OTP code sent to the user',
                'required' => true,
                'example' => '1234',
            ],
            'tracking_code' => [
                'description' => 'The tracking code for the OTP request',
                'required' => true,
                'example' => 'f27873b9-23c3-49be-8667-90afd60bd6b9',
            ],
            'otp_type' => [
                'description' => 'The type of OTP (SMS or Email)',
                'required' => true,
                'enum' => OtpType::cases(),
                'example' => OtpType::SIGNIN->value,
            ],
        ];
    }
}
