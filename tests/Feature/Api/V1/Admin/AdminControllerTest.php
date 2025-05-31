<?php

use Illuminate\Testing\Fluent\AssertableJson;

uses(\Tests\AuthTestTrait::class);
beforeEach(function () {
    $this->adminRole = \Spatie\Permission\Models\Role::create([
        'name' => 'admin',
        'label' => 'Admin',
        'guard_name' => 'admin',
    ])->fresh();

    $this->data = \App\Models\Admin::factory()->make()->toArray();
    $this->data['password'] = 'password123';
    $this->data['password_confirmation'] = $this->data['password'];
    $this->data['roles'] = ['admin'];
});
describe('list filters', function (): void {
    it('should filter by name', function () {
        \App\Models\Admin::factory(20)->create();
        $admin = \App\Models\Admin::factory()->create(['name' => 'John Doe']);
         $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['filter[name]' => 'John Doe']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'John Doe']);
    });

    it('should filter by email', function () {
        \App\Models\Admin::factory(20)->create();
        $admin = \App\Models\Admin::factory()->create(['email' => 'admin@example.com']);
         $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['filter[email]' => 'admin@example.com']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['email' => 'admin@example.com']);
    });

    it('should filter by phone', function () {
        \App\Models\Admin::factory(20)->create();
        $admin = \App\Models\Admin::factory()->create(['phone' => '09301234567']);
         $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['filter[phone]' => '09301234567']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['phone' => '09301234567']);
    });

    it('should filter by role', function () {
        $admin = \App\Models\Admin::factory()->create();
        $admin->assignRole('admin');
        \App\Models\Admin::factory(20)->create();
         $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['filter[role]' => 'admin']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => $admin->name]);
    });
    it('should sort by full name', function (): void {
        $admin1 = App\Models\Admin::factory()->create(['name' => '00000A Admin']);
        $admin2 = App\Models\Admin::factory()->create(['name' => '00000B Admin']);
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['sort' => 'name']));
        $response->assertOk()
            ->assertJsonFragment(['name' => '00000A Admin'])
            ->assertJsonFragment(['name' => '00000B Admin'])
            ->assertJsonPath('data.data.0.name', '00000A Admin')
            ->assertJsonPath('data.data.1.name', '00000B Admin');
    });
    it('should sort by created at', function (): void {
        $admin1 = App\Models\Admin::factory()->create(['created_at' => now()->subDays(2)]);
        $admin2 = App\Models\Admin::factory()->create(['created_at' => now()->subDays(1)]);
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['sort' => 'created_at']));
        $response->assertOk()
            ->assertJsonPath('data.data.0.name', $admin1->name)
            ->assertJsonPath('data.data.1.name', $admin2->name);
    });
    it('should sort by updated at', function (): void {
        $admin1 = App\Models\Admin::factory()->create(['updated_at' => now()->subDays(2)]);
        $admin2 = App\Models\Admin::factory()->create(['updated_at' => now()->subDays(1)]);
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['sort' => 'updated_at']));
        $response->assertOk()
            ->assertJsonPath('data.data.0.name', $admin1->name)
            ->assertJsonPath('data.data.1.name', $admin2->name);
    });
});

describe('admin list', function (): void {
    it('should return a list of admins', function () {
        \App\Models\Admin::factory(20)->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index', ['per_page' => 15]));
        $response->assertOk()
            ->assertJsonCount(15, 'data.data')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'phone',
                            'created_at',
                            'updated_at',
                            'roles' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'label',
                                    'guard_name',
                                ],
                            ],
                        ],
                    ]
                ],
            ]);
    });

    it('should return an empty list when no admins exist', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.index'));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    });

    it('should not return admin list without required permissions', function () {
        $this->unauthorized_user();
        $response = $this->getJson(route('api.v1.admin.admins.index'));
        $response->assertForbidden();
    });
});
describe('admin store', function (): void {
    it('should create a new admin', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);

        $response = $this->postJson(route('api.v1.admin.admins.store'), $this->data);
        $response->assertCreated()
            ->assertJsonFragment(['message' => __('messages.created', ['model' => __('messages.models.admin')])]);

        $this->assertDatabaseHas('admins', [
            'email' =>  $this->data['email'],
            'phone' =>  $this->data['phone'],
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => \App\Models\Admin::where('email',  $this->data['email'])->first()->id,
            'role_id'  => \Spatie\Permission\Models\Role::where('name', 'admin')->first()->id,
        ]);
    });

    it('should not create a new admin without required permissions', function () {
        $this->unauthorized_user();
        $response = $this->postJson(route('api.v1.admin.admins.store'),  $this->data);
        $response->assertForbidden();
    });
    it('should not create a new admin with invalid data', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);

        $this->data['email'] = 'invalid-email';
        $response = $this->postJson(route('api.v1.admin.admins.store'),  $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
    it('should not create a new admin with duplicate email', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);
        $existingAdmin = \App\Models\Admin::factory()->create();
        $this->data['email'] = $existingAdmin->email;
        $response = $this->postJson(route('api.v1.admin.admins.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
    it('should not create a new admin with duplicate phone', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);
        $existingAdmin = \App\Models\Admin::factory()->create();

        $this->data['phone'] = $existingAdmin->phone;
        $response = $this->postJson(route('api.v1.admin.admins.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not create a new admin with invalid phone', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);

        $this->data['phone'] = 'invalid-phone';
        $response = $this->postJson(route('api.v1.admin.admins.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not create a new admin with invalid password', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);

        $this->data['password'] = 'short';
        $this->data['password_confirmation'] = $this->data['password'];

        $response = $this->postJson(route('api.v1.admin.admins.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });
    it('should not create a new admin with invalid name', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);
        $this->data['name'] = '';
        $response = $this->postJson(route('api.v1.admin.admins.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
    it('should not create a new admin with invalid role', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_CREATE->value,
        ]);
        $this->data['roles'] = ['invalid-role'];
        $response = $this->postJson(route('api.v1.admin.admins.store'), $this->data);
        $response->assertInvalid(['roles.0']);
    });
});

describe('admin show', function (): void {
    it('should return a specific admin', function () {
        $admin = \App\Models\Admin::factory()->create();
        $admin->assignRole('admin');
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.show', $admin));
        $response->assertOk()
            ->assertJsonFragment(['email' => $admin->email])
            ->assertJson(function (AssertableJson $json) use ($admin): void {
                $json->where('data.id', $admin->id)
                    ->where('data.name', $admin->name)
                    ->where('data.email', $admin->email)
                    ->where('data.phone', $admin->phone)
                    ->where('data.roles', [
                        [
                            'id' => $this->adminRole->id,
                            'name' => $this->adminRole->name,
                            'label' => $this->adminRole->label,
                        ],
                    ])
                    ->etc();
            })
        ;
    });

    it('should not return a specific admin without required permissions', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->unauthorized_user();
        $response = $this->getJson(route('api.v1.admin.admins.show', $admin));
        $response->assertForbidden();
    });

    it('should return 404 for non-existing admin', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_VIEW->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.admins.show', 9999));
        $response->assertNotFound();
    });
});

describe('admin update', function (): void {
    it('should update an existing admin', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertOk()
            ->assertJsonFragment(['email' => $this->data['email']])
        ->assertJsonFragment(['message' => __('messages.updated', ['model' => __('messages.models.admin')])]);

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'email' => $this->data['email'],
            'phone' => $this->data['phone'],
        ]);
    });
    it('should update an existing admin without changing password', function () {
        $admin = \App\Models\Admin::factory()->create();
        $passowrd = $admin->password;
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        unset($this->data['password'], $this->data['password_confirmation']);
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertOk()
            ->assertJsonFragment(['email' => $this->data['email']]);

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'email' => $this->data['email'],
            'phone' => $this->data['phone'],
            'password' => $passowrd,
        ]);
    });
    it('should not update an admin without required permissions', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->unauthorized_user();
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertForbidden();
    });

    it('should not update an admin with invalid data', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $this->data['email'] = 'invalid-email';

        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('should not update an admin with duplicate email', function () {
        $admin1 = \App\Models\Admin::factory()->create();
        $admin2 = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $this->data['email'] = $admin2->email;
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin1), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('should not update an admin with duplicate phone', function () {
        $admin1 = \App\Models\Admin::factory()->create();
        $admin2 = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $this->data['phone'] = $admin2->phone;
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin1), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not update an admin with invalid phone', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $this->data['phone'] = 'invalid-phone';
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not update an admin with invalid password', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $this->data['password'] = 'short';
        $this->data['password_confirmation'] = $this->data['password'] ;
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });
    it('should not update an admin with invalid name', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $this->data['name'] = '';
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
    it('should not update an admin with invalid role', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_UPDATE->value,
        ]);
        $this->data['roles'] = ['invalid-role'];
        $response = $this->putJson(route('api.v1.admin.admins.update', $admin), $this->data);
        $response->assertInvalid(['roles.0']);
    });
    it('can update itslef', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_DELETE->value,
        ]);
        $data = [
            'name' => 'Updated Name',
            'email' => $this->user->email,
            'phone' => $this->user->phone,
        ];
        $response = $this->putJson(route('api.v1.admin.admins.update', $this->user),$data);
        $response->assertSuccessful();
        $this->assertDatabaseHas('admins', [
            'id' => $this->user->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);
    });
    it('should not update another super admin', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_DELETE->value,
        ]);
        $admin = \App\Models\Admin::factory()
            ->isSuperAdmin()
            ->create();
        $response = $this->putJson(route('api.v1.admin.admins.update',$admin),$this->data);
        $response->assertForbidden();

        $this->admin_user();
        $response = $this->putJson(route('api.v1.admin.admins.update',$admin),$this->data);
        $response->assertForbidden();
    });
});

describe('admin destroy', function (): void {
    it('should delete an existing admin', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_DELETE->value,
        ]);
        $response = $this->deleteJson(route('api.v1.admin.admins.destroy', $admin));
        $response->assertNoContent();

        $this->assertDatabaseMissing('admins', [
            'id' => $admin->id,
        ]);
    });

    it('should not delete an admin without required permissions', function () {
        $admin = \App\Models\Admin::factory()->create();
        $this->unauthorized_user();
        $response = $this->deleteJson(route('api.v1.admin.admins.destroy', $admin));
        $response->assertForbidden();
    });
    it('should not delete itslef', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_DELETE->value,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.admins.destroy', $this->user));
        $response->assertForbidden();
    });
    it('should not delete another super admin', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_DELETE->value,
        ]);
        $admin = \App\Models\Admin::factory()
            ->isSuperAdmin()
            ->create();
        $response = $this->deleteJson(route('api.v1.admin.admins.destroy', $admin));
        $response->assertForbidden();

        $this->admin_user();
        $response = $this->deleteJson(route('api.v1.admin.admins.destroy', $admin));
        $response->assertForbidden();
    });

    it('should not delete a non-existing admin', function () {
        $this->authorized_user([
            App\Enums\PermissionEnum::ADMIN_DELETE->value,
        ]);
        $response = $this->deleteJson(route('api.v1.admin.admins.destroy', 9999));
        $response->assertNotFound();
    });
});
