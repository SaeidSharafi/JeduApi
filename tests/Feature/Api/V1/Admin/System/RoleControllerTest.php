<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);
it('can list roles', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_VIEW_ANY->value]);
    $this
        ->getJson(route('api.v1.admin.roles.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'label',
                    ],
                ],
            ],
        ]);
});

it('can create a role', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_CREATE->value]);
    $data = [
        'name'        => 'TestRole', 'label' => 'Test Role',
        'guard_name'  => 'staff',
        'permissions' => [
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
            App\Enums\PermissionEnum::STAFF_VIEW->value,
        ],
    ];
    $this
        ->postJson(route('api.v1.admin.roles.store'), $data)
        ->assertCreated();

    $this->assertDatabaseHas('roles', [
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $permission1id = Spatie\Permission\Models\Permission::query()
        ->where('name', App\Enums\PermissionEnum::STAFF_VIEW_ANY->value)
        ->first()
        ->id;
    $permission2id = Spatie\Permission\Models\Permission::query()
        ->where('name', App\Enums\PermissionEnum::STAFF_VIEW->value)
        ->first()
        ->id;
    $role = Spatie\Permission\Models\Role::query()
        ->where('name', 'TestRole')
        ->first();
    $this->assertDatabaseHas('role_has_permissions', [
        'permission_id' => $permission1id,
        'role_id'       => $role->id,
    ]);

    $this->assertDatabaseHas('role_has_permissions', [
        'permission_id' => $permission2id,
        'role_id'       => $role->id,
    ]);
});

it('can show a role', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_VIEW->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $role->syncPermissions([
        App\Enums\PermissionEnum::STAFF_VIEW->value,
    ]);
    $this
        ->getJson(route('api.v1.admin.roles.show', $role))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'label',
                'permissions' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
            ],
        ])
        ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($role): void {
            $json->where('data.id', $role->id)
                ->where('data.name', 'TestRole')
                ->has('data.permissions', 1)
                ->where('data.permissions.0.name', App\Enums\PermissionEnum::STAFF_VIEW->value)
                ->etc();
        });

});

it('can update a role', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_UPDATE->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $data = [
        'name'        => 'UpdatedRole',
        'label'       => 'Updated Role Label',
        'guard_name'  => 'staff',
        'permissions' => [
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
            App\Enums\PermissionEnum::STAFF_VIEW->value,
        ],
    ];
    $this
        ->putJson(route('api.v1.admin.roles.update', $role), $data)
        ->assertOk();
});
it('can update a role without changing its name', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_UPDATE->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $data = [
        'name'        => 'TestRole', // Keep the same name
        'label'       => 'Updated Role Label',
        'guard_name'  => 'staff',
        'permissions' => [
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ],
    ];
    $this
        ->putJson(route('api.v1.admin.roles.update', $role), $data)
        ->assertOk();

    $this->assertDatabaseHas('roles', [
        'name'  => 'TestRole',
        'label' => 'Updated Role Label',
    ]);
});
it('can create a role with a staff guard by default', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_CREATE->value]);
    $data = [
        'name'        => 'GuardRole',
        'label'       => 'Guard Role',
        'permissions' => [],
    ];
    $this
        ->postJson(route('api.v1.admin.roles.store'), $data)
        ->assertCreated();

    $this->assertDatabaseHas('roles', [
        'name'       => 'GuardRole',
        'guard_name' => 'staff',
    ]);
});
it('can not update it\'s own role', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_UPDATE->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $this->user->assignRole($role);
    $data = [
        'name'        => 'UpdatedRole',
        'label'       => 'Updated Role Label',
        'guard_name'  => 'staff',
        'permissions' => [
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
            App\Enums\PermissionEnum::STAFF_VIEW->value,
        ],
    ];
    $this
        ->putJson(route('api.v1.admin.roles.update', $role), $data)
        ->assertForbidden();
});
it('can delete a role', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_DELETE->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $this
        ->deleteJson(route('api.v1.admin.roles.destroy', $role))
        ->assertNoContent();
});
it('can not delete it\'s own role', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_DELETE->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $this->user->assignRole($role);
    $this
        ->deleteJson(route('api.v1.admin.roles.destroy', $role))
        ->assertForbidden();
});
