<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\System\OtpType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OtpRequest extends FormRequest
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
            'identifier' => ['required', 'string'],
            'otp_type'   => ['required', 'string', Rule::enum(OtpType::class)],
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
                'description' => 'The phone number or email address of the user',
                'required'    => true,
                'example'     => '09351234567',
            ],
            'otp_type' => [
                'description' => 'The type of OTP to send (login/registration or password reset)',
                'required'    => true,
                'enum'        => OtpType::cases(),
                'example'     => OtpType::SIGNIN->value,
            ],
        ];
    }
}
