<?php

declare(strict_types=1);

use Illuminate\Testing\Fluent\AssertableJson;

uses(Tests\AuthTestTrait::class);
beforeEach(function (): void {
    $this->adminRole = Spatie\Permission\Models\Role::create([
        'name'       => 'admin',
        'label'      => 'Admin',
        'guard_name' => 'staff',
    ])->fresh();

    $this->data                          = App\Models\Staff::factory()->make()->toArray();
    $this->data['password']              = 'password123';
    $this->data['password_confirmation'] = $this->data['password'];
    $this->data['roles']                 = ['admin'];
});
describe('list filters', function (): void {
    it('should filter by name', function (): void {
        App\Models\Staff::factory(20)->create();
        $staff = App\Models\Staff::factory()->create(['name' => 'John Doe']);
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['filter[name]' => 'John Doe']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'John Doe']);
    });

    it('should filter by email', function (): void {
        App\Models\Staff::factory(20)->create();
        $staff = App\Models\Staff::factory()->create(['email' => 'admin@example.com']);
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['filter[email]' => 'admin@example.com']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['email' => 'admin@example.com']);
    });

    it('should filter by phone', function (): void {
        App\Models\Staff::factory(20)->create();
        $staff = App\Models\Staff::factory()->create(['phone' => '09301234567']);
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['filter[phone]' => '09301234567']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['phone' => '09301234567']);
    });

    it('should filter by role', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $staff->assignRole('admin');
        App\Models\Staff::factory(20)->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['filter[role]' => 'admin']));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => $staff->name]);
    });
    it('should sort by full name', function (): void {
        $staff1 = App\Models\Staff::factory()->create(['name' => '00000A Admin']);
        $staff2 = App\Models\Staff::factory()->create(['name' => '00000B Admin']);
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['sort' => 'name']));
        $response->assertOk()
            ->assertJsonFragment(['name' => '00000A Admin'])
            ->assertJsonFragment(['name' => '00000B Admin'])
            ->assertJsonPath('data.data.0.name', '00000A Admin')
            ->assertJsonPath('data.data.1.name', '00000B Admin');
    });
    it('should sort by created at', function (): void {
        $staff1 = App\Models\Staff::factory()->create(['created_at' => now()->subDays(2)]);
        $staff2 = App\Models\Staff::factory()->create(['created_at' => now()->subDays(1)]);
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['sort' => 'created_at']));
        $response->assertOk()
            ->assertJsonPath('data.data.0.name', $staff1->name)
            ->assertJsonPath('data.data.1.name', $staff2->name);
    });
    it('should sort by updated at', function (): void {
        $staff1 = App\Models\Staff::factory()->create(['updated_at' => now()->subDays(2)]);
        $staff2 = App\Models\Staff::factory()->create(['updated_at' => now()->subDays(1)]);
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['sort' => 'updated_at']));
        $response->assertOk()
            ->assertJsonPath('data.data.0.name', $staff1->name)
            ->assertJsonPath('data.data.1.name', $staff2->name);
    });
});

describe('admin list', function (): void {
    it('should return a list of staff', function (): void {
        App\Models\Staff::factory(20)->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index', ['per_page' => 15]));
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
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    });

    it('should return an empty list when no staff exist', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.index'));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data');
    });

    it('should not return staff list without required permissions', function (): void {
        $this->unauthorized_user();
        $response = $this->getJson(route('api.v1.admin.staff.index'));
        $response->assertForbidden();
    });
});
describe('admin store', function (): void {
    it('should create a new admin', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);

        $response = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertCreated()
            ->assertJsonFragment(['message' => __('messages.created', ['model' => __('messages.models.staff')])]);

        $this->assertDatabaseHas('staff', [
            'email' => $this->data['email'],
            'phone' => $this->data['phone'],
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => App\Models\Staff::where('email', $this->data['email'])->first()->id,
            'role_id'  => Spatie\Permission\Models\Role::where('name', 'admin')->first()->id,
        ]);
    });

    it('should not create a new staff without required permissions', function (): void {
        $this->unauthorized_user();
        $response = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertForbidden();
    });
    it('should not create a new staff with invalid data', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);

        $this->data['email'] = 'invalid-email';
        $response            = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
    it('should not create a new staff with duplicate email', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);
        $existingAdmin       = App\Models\Staff::factory()->create();
        $this->data['email'] = $existingAdmin->email;
        $response            = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
    it('should not create a new staff with duplicate phone', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);
        $existingAdmin = App\Models\Staff::factory()->create();

        $this->data['phone'] = $existingAdmin->phone;
        $response            = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not create a new staff with invalid phone', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);

        $this->data['phone'] = 'invalid-phone';
        $response            = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not create a new staff with invalid password', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);

        $this->data['password']              = 'short';
        $this->data['password_confirmation'] = $this->data['password'];

        $response = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });
    it('should not create a new staff with invalid name', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);
        $this->data['name'] = '';
        $response           = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
    it('should not create a new staff with invalid role', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_CREATE->value,
        ]);
        $this->data['roles'] = ['invalid-role'];
        $response            = $this->postJson(route('api.v1.admin.staff.store'), $this->data);
        $response->assertInvalid(['roles.0']);
    });
});

describe('admin show', function (): void {
    it('should return a specific admin', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $staff->assignRole('admin');
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.show', $staff));
        $response->assertOk()
            ->assertJsonFragment(['email' => $staff->email])
            ->assertJson(function (AssertableJson $json) use ($staff): void {
                $json->where('data.id', $staff->id)
                    ->where('data.name', $staff->name)
                    ->where('data.email', $staff->email)
                    ->where('data.phone', $staff->phone)
                    ->where('data.roles', [
                        [
                            'id'    => $this->adminRole->id,
                            'name'  => $this->adminRole->name,
                            'label' => $this->adminRole->label,
                        ],
                    ])
                    ->etc();
            });
    });

    it('should not return a specific staff without required permissions', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->unauthorized_user();
        $response = $this->getJson(route('api.v1.admin.staff.show', $staff));
        $response->assertForbidden();
    });

    it('should return 404 for non-existing admin', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_VIEW->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.staff.show', 9999));
        $response->assertNotFound();
    });
});

describe('admin update', function (): void {
    it('should update an existing admin', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $response = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertOk()
            ->assertJsonFragment(['email' => $this->data['email']])
            ->assertJsonFragment(['message' => __('messages.updated', ['model' => __('messages.models.staff')])]);

        $this->assertDatabaseHas('staff', [
            'id'    => $staff->id,
            'email' => $this->data['email'],
            'phone' => $this->data['phone'],
        ]);
    });
    it('should update an existing staff without changing password', function (): void {
        $staff    = App\Models\Staff::factory()->create();
        $passowrd = $staff->password;
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        unset($this->data['password'], $this->data['password_confirmation']);
        $response = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertOk()
            ->assertJsonFragment(['email' => $this->data['email']]);

        $this->assertDatabaseHas('staff', [
            'id'       => $staff->id,
            'email'    => $this->data['email'],
            'phone'    => $this->data['phone'],
            'password' => $passowrd,
        ]);
    });
    it('should not update an staff without required permissions', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->unauthorized_user();
        $response = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertForbidden();
    });

    it('should not update an staff with invalid data', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $this->data['email'] = 'invalid-email';

        $response = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('should not update an staff with duplicate email', function (): void {
        $staff1 = App\Models\Staff::factory()->create();
        $staff2 = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $this->data['email'] = $staff2->email;
        $response            = $this->putJson(route('api.v1.admin.staff.update', $staff1), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('should not update an staff with duplicate phone', function (): void {
        $staff1 = App\Models\Staff::factory()->create();
        $staff2 = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $this->data['phone'] = $staff2->phone;
        $response            = $this->putJson(route('api.v1.admin.staff.update', $staff1), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not update an staff with invalid phone', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $this->data['phone'] = 'invalid-phone';
        $response            = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });
    it('should not update an staff with invalid password', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $this->data['password']              = 'short';
        $this->data['password_confirmation'] = $this->data['password'];
        $response                            = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });
    it('should not update an staff with invalid name', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $this->data['name'] = '';
        $response           = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
    it('should not update an staff with invalid role', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_UPDATE->value,
        ]);
        $this->data['roles'] = ['invalid-role'];
        $response            = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertInvalid(['roles.0']);
    });
    it('can update itslef', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_DELETE->value,
        ]);
        $data = [
            'name'  => 'Updated Name',
            'email' => $this->user->email,
            'phone' => $this->user->phone,
        ];
        $response = $this->putJson(route('api.v1.admin.staff.update', $this->user), $data);
        $response->assertSuccessful();
        $this->assertDatabaseHas('staff', [
            'id'    => $this->user->id,
            'name'  => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);
    });
    it('should not update another super admin', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_DELETE->value,
        ]);
        $staff = App\Models\Staff::factory()
            ->isSuperAdmin()
            ->create();
        $response = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertForbidden();

        $this->admin_user();
        $response = $this->putJson(route('api.v1.admin.staff.update', $staff), $this->data);
        $response->assertForbidden();
    });
});

describe('admin destroy', function (): void {
    it('should delete an existing admin', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_DELETE->value,
        ]);
        $response = $this->deleteJson(route('api.v1.admin.staff.destroy', $staff));
        $response->assertNoContent();

        $this->assertDatabaseMissing('staff', [
            'id' => $staff->id,
        ]);
    });

    it('should not delete an staff without required permissions', function (): void {
        $staff = App\Models\Staff::factory()->create();
        $this->unauthorized_user();
        $response = $this->deleteJson(route('api.v1.admin.staff.destroy', $staff));
        $response->assertForbidden();
    });
    it('should not delete itslef', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_DELETE->value,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.staff.destroy', $this->user));
        $response->assertForbidden();
    });
    it('should not delete another super admin', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_DELETE->value,
        ]);
        $staff = App\Models\Staff::factory()
            ->isSuperAdmin()
            ->create();
        $response = $this->deleteJson(route('api.v1.admin.staff.destroy', $staff));
        $response->assertForbidden();

        $this->admin_user();
        $response = $this->deleteJson(route('api.v1.admin.staff.destroy', $staff));
        $response->assertForbidden();
    });

    it('should not delete a non-existing admin', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::STAFF_DELETE->value,
        ]);
        $response = $this->deleteJson(route('api.v1.admin.staff.destroy', 9999));
        $response->assertNotFound();
    });
});
