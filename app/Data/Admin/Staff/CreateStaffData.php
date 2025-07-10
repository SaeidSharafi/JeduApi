<?php

declare(strict_types=1);

namespace App\Data\Admin\Staff;

use App\Rules\IranMobilePhoneRule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateStaffData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $password,
        public array $roles = [],
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:staff,email'],
            'phone'    => ['required', new IranMobilePhoneRule(), 'unique:staff,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles'    => ['array'],
            'roles.*'  => ['exists:roles,name'],
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
            'name' => [
                'description' => 'The name of the staff.',
                'example'     => 'John Doe',
            ],
            'email' => [
                'description' => 'The email address of the staff.',
                'example'     => 'zbailey@example.net',
            ],
            'phone' => [
                'description' => 'The phone number of the staff.',
                'example'     => '09123456789',
            ],
            'password' => [
                'description' => 'The password for the staff account.',
                'example'     => 'securepassword123',
            ],
            'roles' => [
                'description' => 'The roles assigned to the admin.',
                'example'     => ['super-admin', 'editor'],
            ],
            'roles.*' => [
                'description' => 'Array of role names assigned to the admin.',
                'example'     => 'editor',
            ],
        ];
    }
}
