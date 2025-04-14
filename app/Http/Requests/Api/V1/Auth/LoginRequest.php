<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Rules\EmailOrPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', new EmailOrPhoneRule],
            'password' => ['sometimes', 'string'],
        ];
    }

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
