<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Policies\Admin\EnrollmentPolicy;

describe('EnrollmentPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new EnrollmentPolicy();
    });

    it('allows view any with permission', function (): void {
        $staff = Staff::factory()->create();
        $role  = Spatie\Permission\Models\Role::create([
            'name'       => 'test_role',
            'label'      => 'Test Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::ENROLLMENT_VIEW_ANY->value);
        $staff->assignRole($role);

        expect($this->policy->viewAny($staff->fresh()))->toBeTrue();
    });

    it('denies view any without permission', function (): void {
        $staff = Staff::factory()->create();

        expect($this->policy->viewAny($staff))->toBeFalse();
    });

    it('allows view with permission', function (): void {
        $staff      = Staff::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $role       = Spatie\Permission\Models\Role::create([
            'name'       => 'test_role',
            'label'      => 'Test Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::ENROLLMENT_VIEW->value);
        $staff->assignRole($role);

        expect($this->policy->view($staff->fresh(), $enrollment))->toBeTrue();
    });

    it('allows update with permission', function (): void {
        $staff      = Staff::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $role       = Spatie\Permission\Models\Role::create([
            'name'       => 'test_role',
            'label'      => 'Test Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::ENROLLMENT_UPDATE->value);
        $staff->assignRole($role);

        expect($this->policy->update($staff->fresh(), $enrollment))->toBeTrue();
    });

    it('allows change status with permission', function (): void {
        $staff      = Staff::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $role       = Spatie\Permission\Models\Role::create([
            'name'       => 'test_role',
            'label'      => 'Test Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::ENROLLMENT_UPDATE->value);
        $staff->assignRole($role);

        expect($this->policy->changeStatus($staff->fresh(), $enrollment))->toBeTrue();
    });

    it('allows retry provisioning with permission', function (): void {
        $staff      = Staff::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $role       = Spatie\Permission\Models\Role::create([
            'name'       => 'test_role',
            'label'      => 'Test Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::ENROLLMENT_RETRY_PROVISION->value);
        $staff->assignRole($role);

        expect($this->policy->retryProvisioning($staff->fresh(), $enrollment))->toBeTrue();
    });

    it('allows delete with permission', function (): void {
        $staff      = Staff::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $role       = Spatie\Permission\Models\Role::create([
            'name'       => 'test_role',
            'label'      => 'Test Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::ENROLLMENT_DELETE->value);
        $staff->assignRole($role);

        expect($this->policy->delete($staff->fresh(), $enrollment))->toBeTrue();
    });
});
