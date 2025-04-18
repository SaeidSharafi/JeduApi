<?php

namespace App\Http\Requests\Auth;

use App\Rules\EmailOrPhoneRule;
use Illuminate\Foundation\Http\FormRequest;

class InitiateAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', new EmailOrPhoneRule],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'identifier' => [
                'description' => 'Email or Phone number of the user',
                'required' => true,
                'example' => '09351234567',
            ],
        ];
    }
}
