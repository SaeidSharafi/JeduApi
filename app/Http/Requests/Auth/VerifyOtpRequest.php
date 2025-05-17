<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\OtpType;
use App\Rules\EmailOrPhoneRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', new EmailOrPhoneRule()],
            'otp_code' => ['required', 'integer', 'min:'.config('otp.code_min'), 'max:'.config('otp.code_max')],
            'tracking_code' => ['required', 'string'],
            'otp_type' => ['required', 'string', Rule::enum(OtpType::class)],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
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
