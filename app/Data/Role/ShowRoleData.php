<?php

declare(strict_types=1);

namespace App\Data\Role;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class ShowRoleData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $label,
        #[DataCollectionOf(PermissionData::class)]
        public DataCollection $permissions,
    ) {
    }

    public static function fromModel(Role $role): ShowRoleData
    {
        return ShowRoleData::from(
            [
                'id'          => $role->id,
                'name'        => $role->name,
                'label'       => $role->label,
                'permissions' => $role->relationLoaded('permissions')
                    ? $role->permissions
                    : []
            ]
        );
    }
}
