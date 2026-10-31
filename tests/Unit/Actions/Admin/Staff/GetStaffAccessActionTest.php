<?php

declare(strict_types=1);

use App\Actions\Admin\Staff\GetStaffAccessAction;
use App\Models\Staff;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

mutates(GetStaffAccessAction::class);

describe('GetStaffAccessAction', function (): void {
    beforeEach(function (): void {
        $this->action = app(GetStaffAccessAction::class);
    });

    it('returns the role permissions for a regular staff member', function (): void {
        $staff = Staff::factory()->create();
        $role  = Role::create(['name' => 'editor', 'label' => 'Editor', 'guard_name' => 'staff']);
        $role->syncPermissions(['staff.view', 'staff.update']);
        $staff->assignRole($role);

        $access = $this->action->handle($staff);

        expect($access['is_admin'])->toBeFalse()
            ->and($access['roles'])->toBe(['editor'])
            ->and($access['permissions'])->toBe(['staff.update', 'staff.view']);
    });

    it('returns every staff permission for a super admin', function (): void {
        $staff = Staff::factory()->isSuperAdmin()->create();

        $access = $this->action->handle($staff);

        $allStaffPermissions = Permission::query()
            ->where('guard_name', 'staff')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        expect($access['is_admin'])->toBeTrue()
            ->and($access['roles'])->toBe([])
            ->and($access['permissions'])->toBe($allStaffPermissions)
            ->and($access['permissions'])->toContain('staff.view');
    });
});
