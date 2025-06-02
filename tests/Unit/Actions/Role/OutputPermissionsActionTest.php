<?php

declare(strict_types=1);

use App\Actions\Role\OutputPermissionsAction;
use App\Data\Role\PermissionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);
beforeEach(function () {
    Permission::query()->truncate();
});
describe('OutputPermissionsAction', function (): void {

    it('should group permissions by resource correctly', function () {

        $categoryCreate = Permission::create(['name' => 'category.create', 'guard_name' => 'staff']);
        $categoryView = Permission::create(['name' => 'category.view', 'guard_name' => 'staff']);
        $courseCreate = Permission::create(['name' => 'course.create', 'guard_name' => 'staff']);

        $action = new OutputPermissionsAction();
        $result = $action->handle('staff');

        expect($result)->toHaveKey('category')
            ->and($result)->toHaveKey('course')
            ->and($result['category']['label'])->toBe(__('auth.permission.resource.category'))
            ->and($result['category']['resource'])->toBe('category')
            ->and($result['category']['permissions'])->toHaveCount(2)
            ->and($result['course']['label'])->toBe(__('auth.permission.resource.course'))
            ->and($result['course']['resource'])->toBe('course')
            ->and($result['course']['permissions'])->toHaveCount(1);
    });

    it('should filter permissions by guard correctly', function () {
        // Create permissions for different guards
        Permission::create(['name' => 'staff.view', 'guard_name' => 'staff']);
        Permission::create(['name' => 'user.profile', 'guard_name' => 'user']);
        Permission::create(['name' => 'admin.manage', 'guard_name' => 'admin']);


        $action = new OutputPermissionsAction();

        // Test staff guard
        $staffResult = $action->handle('staff');
        expect($staffResult)->toHaveKey('staff')
            ->and($staffResult)->not->toHaveKey('user')
            ->and($staffResult)->not->toHaveKey('admin');


        $userResult = $action->handle('user');
        expect($userResult)->toHaveKey('user')
            ->and($userResult)->not->toHaveKey('staff')
            ->and($userResult)->not->toHaveKey('admin');
    });

    it('should handle custom permissions', function () {
        // Create a permission with a custom action that doesn't exist in standard actions
        $customPermission = Permission::create(['name' => 'staff.impersonate', 'guard_name' => 'staff']);

        $action = new OutputPermissionsAction();
        $result = $action->handle('staff');

        expect($result)->toHaveKey('staff')
            ->and($result['staff']['permissions'])->toHaveCount(1)
            ->and($result['staff']['permissions'][0]['name'])->toBe('staff.impersonate')
            ->and($result['staff']['permissions'][0]['label'])->toBe(__('auth.permission.custom.staff.impersonate'))
            ->and($result['staff']['permissions'][0]['resourceKey'])->toBe('staff');
    });

    it('should handle multiple custom permissions for same resource', function () {
        // Create multiple custom permissions
        Permission::create(['name' => 'staff.impersonate', 'guard_name' => 'staff']);
        Permission::create(['name' => 'staff.manage_roles', 'guard_name' => 'staff']);
        Permission::create(['name' => 'staff.view', 'guard_name' => 'staff']); // standard action

        $action = new OutputPermissionsAction();
        $result = $action->handle('staff');

        expect($result)->toHaveKey('staff')
            ->and($result['staff']['permissions'])->toHaveCount(3);

        // Find the custom permissions in the result
        $permissions = collect($result['staff']['permissions']);

        $impersonatePermission = $permissions->firstWhere('name', 'staff.impersonate');
        $manageRolesPermission = $permissions->firstWhere('name', 'staff.manage_roles');
        $viewPermission = $permissions->firstWhere('name', 'staff.view');

        expect($impersonatePermission['label'])->toBe(__('auth.permission.custom.staff.impersonate'))
            ->and($manageRolesPermission['label'])->toBe(__('auth.permission.custom.staff.manage_roles'))
            ->and($viewPermission['label'])->toBe(__('auth.permission.action.view'));
    });

    it('should return empty array when no permissions exist for guard', function () {
        // Don't create any permissions
        $action = new OutputPermissionsAction();
        $result = $action->handle('nonexistent_guard');

        expect($result)->toBe([]);
    });

    it('should handle permissions with malformed names gracefully', function () {
        // Create a permission with malformed name (no dot separator)
        Permission::create(['name' => 'malformed_permission_name', 'guard_name' => 'staff']);

        $action = new OutputPermissionsAction();
        $result = $action->handle('staff');

        // Should handle gracefully and group by empty resource key
        expect($result)->toHaveKey('custom_permission')
            ->and($result['custom_permission']['permissions'])->toHaveCount(1)
            ->and($result['custom_permission']['permissions'][0]['name'])->toBe('malformed_permission_name')
            ->and($result['custom_permission']['permissions'][0]['resourceKey'])->toBe('custom_permission');
    });

    it('should use default staff guard when no guard specified', function () {
        Permission::create(['name' => 'staff.view', 'guard_name' => 'staff']);
        Permission::create(['name' => 'user.view', 'guard_name' => 'user']);


        $action = new OutputPermissionsAction();

        // Test without specifying guard (should default to 'staff')
        $result = $action->handle();

        expect($result)->toHaveKey('staff')
            ->and($result)->not->toHaveKey('user');
    });

    it('should properly format PermissionData structure', function () {
        Permission::create(['name' => 'course.create', 'guard_name' => 'staff']);

        $action = new OutputPermissionsAction();
        $result = $action->handle('staff');

        $permission = $result['course']['permissions'][0];

        // Verify PermissionData structure
        expect($permission)->toHaveKeys(['id', 'name', 'resource', 'resourceKey', 'label'])
            ->and($permission['name'])->toBe('course.create')
            ->and($permission['resource'])->toBe(__('auth.permission.resource.course'))
            ->and($permission['resourceKey'])->toBe('course')
            ->and($permission['label'])->toBe(__('auth.permission.action.create'))
            ->and($permission['id'])->toBeInt();
    });
});
