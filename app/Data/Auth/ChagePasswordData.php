<?php

namespace App\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class ChagePasswordData extends Data
{
    public function __construct(
        public ?string $current_password,
        public string $password,
        public string $password_confirmation
    )
    {
    }

    /**
     * Define validation rules for the request.
     */
    public static function rules(?ValidationContext $context = null): array
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
