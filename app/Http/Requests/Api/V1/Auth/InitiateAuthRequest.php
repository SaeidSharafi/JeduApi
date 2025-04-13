<?php

namespace App\Http\Requests\Api\V1\Auth;

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
            'identifier' => ['required', 'string', new EmailOrPhoneRule()],
        ];
    }
}
