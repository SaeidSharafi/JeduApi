<?php

declare(strict_types=1);

namespace App\Data\Admin\Role;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreateRoleData extends Data
{
    public function __construct(
        public string $name,
        public string $label,
        public array $permissions,
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'name'          => ['required', 'string', 'alpha_num', 'max:60', 'unique:roles,name'],
            'label'         => ['required', 'string', 'max:255'],
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public static function attributes(...$args): array
    {
        return [
            'name'        => __('validation.attributes.role.name'),
            'label'       => __('validation.attributes.role.label'),
            'permissions' => __('validation.attributes.role.permissions'),
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
                'description' => 'The unique name of the role.',
                'example'     => 'admin',
            ],
            'label' => [
                'description' => 'A human-readable label for the role.',
                'example'     => 'Administrator',
            ],
            'permissions' => [
                'description' => 'An array of permission names that this role has.',
                'example'     => ['course.view', 'course.create'],
            ],
            'permissions.*' => [
                'description' => 'A permission name.',
                'example'     => 'course.view',
            ],
        ];
    }
}
