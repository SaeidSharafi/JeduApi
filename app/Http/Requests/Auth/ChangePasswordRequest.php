<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['nullable', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
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
            'current_password' => [
                'description' => 'The current password of the user.',
                'required'    => true,
                'example'     => 'current_password',
            ],
            'password'              => [
                'description' => 'The new password for the user.',
                'required'    => true,
                'example'     => '12345678',
            ],
            'password_confirmation' => [
                'description' => 'The new password confirmation for the user.',
                'required'    => true,
                'example'     => '12345678',
            ],
        ];
    }
}
