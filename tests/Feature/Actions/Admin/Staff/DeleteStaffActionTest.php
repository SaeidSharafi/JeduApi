<?php

declare(strict_types=1);

use App\Actions\Admin\Staff\DeleteStaffAction;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('deletes a staff member', function (): void {
    $staff = Staff::factory()->create();

    app(DeleteStaffAction::class)->handle($staff);

    expect(Staff::query()->whereKey($staff->id)->exists())->toBeFalse();
});

it('revokes sanctum tokens when deleting a staff member', function (): void {
    $staff = Staff::factory()->create();
    $staff->createToken('test-token');

    app(DeleteStaffAction::class)->handle($staff);

    expect(DB::table('personal_access_tokens')
        ->where('tokenable_id', $staff->id)
        ->where('tokenable_type', Staff::class)
        ->exists())->toBeFalse();
});

it('auto-detaches roles and permissions when deleting a staff member', function (): void {
    $staff      = Staff::factory()->create();
    $role       = Role::create(['name' => 'delete_staff_test_role', 'label' => 'DeleteStaffTest', 'guard_name' => 'staff']);
    $permission = Permission::create(['name' => 'delete_staff_test_permission', 'guard_name' => 'staff']);
    $role->syncPermissions([$permission]);
    $staff->assignRole($role);
    $staff->givePermissionTo($permission);

    app(DeleteStaffAction::class)->handle($staff);

    expect(DB::table('model_has_roles')
        ->where('model_id', $staff->id)
        ->where('model_type', Staff::class)
        ->exists())->toBeFalse();

    expect(DB::table('model_has_permissions')
        ->where('model_id', $staff->id)
        ->where('model_type', Staff::class)
        ->exists())->toBeFalse();
});
