<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\OtpType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'otp_type' => ['required', 'string', Rule::enum(OtpType::class)],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'identifier' => [
                'description' => 'The phone number or email address of the user',
                'required' => true,
                'example' => '09351234567',
            ],
            'otp_type' => [
                'description' => 'The type of OTP to send (login/registration or password reset)',
                'required' => true,
                'enum' => OtpType::cases(),
                'example' => OtpType::SIGNIN->value,
            ],
        ];
    }
}
