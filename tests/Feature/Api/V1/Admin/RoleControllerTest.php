<?php

declare(strict_types=1);

uses(Tests\AuthTestTrait::class);
it('can list roles', function () {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_VIEW_ANY->value]);
    $this
        ->getJson(route('api.v1.admin.role.index'))
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

it('can create a role', function () {
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
        ->postJson(route('api.v1.admin.role.store'), $data)
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

it('can show a role', function () {
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
        ->getJson(route('api.v1.admin.role.show', $role))
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
        ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($role) {
            $json->where('data.id', $role->id)
                ->where('data.name', 'TestRole')
                ->has('data.permissions', 1)
                ->where('data.permissions.0.name', App\Enums\PermissionEnum::STAFF_VIEW->value)
                ->etc();
        });

});

it('can update a role', function () {
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
        ->putJson(route('api.v1.admin.role.update', $role), $data)
        ->assertOk();
});
it('can not update it\'s own role', function () {
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
        ->putJson(route('api.v1.admin.role.update', $role), $data)
        ->assertForbidden();
});
it('can delete a role', function () {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_DELETE->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $this
        ->deleteJson(route('api.v1.admin.role.destroy', $role))
        ->assertNoContent();
});
it('can not delete it\'s own role', function () {
    $this->authorized_user([App\Enums\PermissionEnum::ROLE_DELETE->value]);
    $role = Spatie\Permission\Models\Role::create([
        'name'       => 'TestRole',
        'label'      => 'Test Role',
        'guard_name' => 'staff',
    ]);
    $this->user->assignRole($role);
    $this
        ->deleteJson(route('api.v1.admin.role.destroy', $role))
        ->assertForbidden();
});
