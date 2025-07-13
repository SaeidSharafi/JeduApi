<?php

declare(strict_types=1);

use App\Actions\Admin\Role\OutputPermissionsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class, Tests\AuthTestTrait::class);

describe('permissions listing', function (): void {
    it('should return permissions list for authenticated staff', function () {
        $this->authorized_user();

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk()
            ->assertJson(function (AssertableJson $json): void {
                $json->has('message')
                    ->has('data')
                    ->has('metadata')
                    ->has('data.staff')
                    ->has('data.role')
                    ->has('data.category')
                    ->has('data.course')
                    ->has('data.file')
                    ->has('data.seminar')
                    ->etc();
            });
    });

    it('should group permissions by resource correctly', function () {
        $this->authorized_user();

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk()
            ->assertJson(function (AssertableJson $json): void {
                $json->has('data.staff', function (AssertableJson $staffJson): void {
                    $staffJson->has('label')
                        ->has('resource')
                        ->where('resource', 'staff')
                        ->has('permissions')
                        ->has('permissions.0', function (AssertableJson $permissionJson): void {
                            $permissionJson->has('id')
                                ->has('name')
                                ->has('resource')
                                ->has('resourceKey')
                                ->has('label')
                                ->etc();
                        })
                        ->etc();
                })
                    ->has('data.role', function (AssertableJson $roleJson): void {
                        $roleJson->has('label')
                            ->has('resource')
                            ->where('resource', 'role')
                            ->has('permissions')
                            ->etc();
                    })
                    ->etc();
            });
    });

    it('should include permission details in response', function () {
        $this->authorized_user();

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk();

        $data = $response->json('data');

        // Check that staff permissions are present and properly structured
        expect($data)->toHaveKey('staff')
            ->and($data['staff'])->toHaveKeys(['label', 'resource', 'permissions'])
            ->and($data['staff']['resource'])->toBe('staff')
            ->and($data['staff']['permissions'])->toBeArray()
            ->and($data['staff']['permissions'])->not->toBeEmpty();

        // Check permission structure
        $firstStaffPermission = $data['staff']['permissions'][0];
        expect($firstStaffPermission)->toHaveKeys(['id', 'name', 'resource', 'resourceKey', 'label'])
            ->and($firstStaffPermission['resourceKey'])->toBe('staff')
            ->and($firstStaffPermission['name'])->toContain('staff.');
    });

    it('should not return permissions list without authentication', function () {
        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertUnauthorized();
    });

    it('should handle empty permissions gracefully', function () {
        // Delete all staff permissions
        Permission::where('guard_name', 'staff')->delete();

        $this->authorized_user();

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk()
            ->assertJson(function (AssertableJson $json): void {
                $json->has('message')
                    ->has('data')
                    ->has('metadata')
                    ->where('data', [])
                    ->etc();
            });
    });

    it('should only return permissions for staff guard', function () {
        // Create a permission for a different guard
        Permission::create(['name' => 'permission_for_user_guard', 'guard_name' => 'user']);
        Permission::create(['name' => 'permission_for_staff_guard', 'guard_name' => 'staff']);

        $this->authorized_user();

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk();

        $data = $response->json('data');
        foreach (data_get($data, 'custom_permission.permissions') as $permission) {
            expect($permission['name'])->not->toBe('permission_for_user_guard');
        }
    });

    it('should work with different guard when specified', function () {
        // Create permissions for user guard
        Permission::create(['name' => 'profile.view', 'guard_name' => 'user']);
        Permission::create(['name' => 'profile.update', 'guard_name' => 'user']);

        $this->authorized_user();

        // Test the action directly with a different guard
        $action = new OutputPermissionsAction();
        $result = $action->handle('user');

        expect($result)->toHaveKey('profile')
            ->and($result['profile']['resource'])->toBe('profile')
            ->and($result['profile']['permissions'])->toBeArray()
            ->and($result['profile']['permissions'])->not->toBeEmpty();
    });

    it('should format permission data correctly using PermissionData', function () {
        $this->authorized_user();

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk();

        $data = $response->json('data');

        // Get first permission from any resource
        $firstResource   = array_values($data)[0];
        $firstPermission = $firstResource['permissions'][0];

        // Verify the data structure matches PermissionData format
        expect($firstPermission)->toHaveKeys(['id', 'name', 'resource', 'resourceKey', 'label']);

        // Verify that the resource key is extracted correctly from permission name
        $nameParts = explode('.', $firstPermission['name']);
        expect($firstPermission['resourceKey'])->toBe($nameParts[0]);
    });

    it('should use correct HTTP method', function () {
        $this->authorized_user();

        // GET should work
        $response = $this->getJson(route('api.v1.admin.permission.index'));
        $response->assertOk();

        // POST should not be allowed
        $response = $this->postJson(route('api.v1.admin.permission.index'));
        $response->assertMethodNotAllowed();

        // PUT should not be allowed
        $response = $this->putJson(route('api.v1.admin.permission.index'));
        $response->assertMethodNotAllowed();

        // DELETE should not be allowed
        $response = $this->deleteJson(route('api.v1.admin.permission.index'));
        $response->assertMethodNotAllowed();
    });
});

describe('permissions action integration', function (): void {
    it('should call OutputPermissionsAction correctly', function () {
        $this->authorized_user();

        // Mock the action to verify it's called
        $this->mock(OutputPermissionsAction::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->withNoArgs()
                ->andReturn([
                    'test' => [
                        'label'       => 'Test Resource',
                        'resource'    => 'test',
                        'permissions' => [],
                    ],
                ]);
        });

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk()
            ->assertJsonPath('data.test.label', 'Test Resource')
            ->assertJsonPath('data.test.resource', 'test');
    });

    it('should return response with success format', function () {
        $this->authorized_user();

        $response = $this->getJson(route('api.v1.admin.permission.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data',
                'metadata',
            ]);
    });
});
