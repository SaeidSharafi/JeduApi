<?php

declare(strict_types=1);

namespace App\Data\Staff;

use App\Rules\IranMobilePhoneRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class UpdateStaffData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public ?string $password,
        public array $roles = [],
    ) {}

    public static function rules(ValidationContext $context): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('staff', 'email')->where(function (Builder $query) {
                    $staff = request()->route()->parameter('staff');
                    if ($staff && $staff->id) {
                        $query->whereNot('id', $staff->id);
                    }

                    return $query;
                }),
            ],
            'phone' => ['required', new IranMobilePhoneRule(),
                Rule::unique('staff', 'phone')->where(function (Builder $query) {
                    $staff = request()->route()->parameter('staff');
                    if ($staff && $staff->id) {
                        $query->whereNot('id', $staff->id);
                    }

                    return $query;
                }),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
                'description' => 'The phone number of the admin.',
                'example'     => '09123456789',
            ],
            'password' => [
                'description' => 'The password for the admin account.',
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
