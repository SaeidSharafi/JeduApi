<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\EmailOrPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
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
            'identifier' => ['required', new EmailOrPhoneRule],
            'password' => ['sometimes', 'string'],
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
                'description' => 'Email or Phone number of the user',
                'required' => true,
                'example' => '09351234567',
            ],
            'password' => [
                'description' => 'Password of the user',
                'required' => true,
                'example' => '12345678',
            ],
        ];
    }
}
