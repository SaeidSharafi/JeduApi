<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Rules\IranMobilePhoneRule;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class UpdateStaffProfileData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public ?string $password = null
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('staff', 'email')->ignore(
                    auth('staff')->user()
                ),
            ],
            'phone' => ['required', new IranMobilePhoneRule(),
                Rule::unique('staff', 'phone')->ignore(
                    auth('staff')->user()
                ),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
                'description' => 'The phone number of the admin.',
                'example'     => '09123456789',
            ],
        ];
    }
}
