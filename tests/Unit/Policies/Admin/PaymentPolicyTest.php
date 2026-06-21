<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Payment;
use App\Models\Staff;
use App\Policies\Admin\PaymentPolicy;

describe('PaymentPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy  = new PaymentPolicy();
        $this->payment = Payment::factory()->create();
    });

    // ─── viewAny ────────────────────────────────────────────────────────────

    it('allows view any with permission', function (): void {
        $staff = Staff::factory()->create();
        $role  = Spatie\Permission\Models\Role::create([
            'name'       => 'test_payment_role',
            'label'      => 'Test Payment Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::PAYMENT_VIEW_ANY->value);
        $staff->assignRole($role);

        expect($this->policy->viewAny($staff->fresh()))->toBeTrue();
    });

    it('denies view any without permission', function (): void {
        $staff = Staff::factory()->create();

        expect($this->policy->viewAny($staff))->toBeFalse();
    });

    // ─── view (inquire) ─────────────────────────────────────────────────────

    it('allows inquire with permission', function (): void {
        $staff = Staff::factory()->create();
        $role  = Spatie\Permission\Models\Role::create([
            'name'       => 'test_payment_role',
            'label'      => 'Test Payment Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::PAYMENT_VIEW->value);
        $staff->assignRole($role);

        expect($this->policy->inquire($staff->fresh(), $this->payment))->toBeTrue();
    });

    it('denies inquire without permission', function (): void {
        $staff = Staff::factory()->create();

        expect($this->policy->inquire($staff, $this->payment))->toBeFalse();
    });

    // ─── refund ─────────────────────────────────────────────────────────────

    it('allows refund with update permission', function (): void {
        $staff = Staff::factory()->create();
        $role  = Spatie\Permission\Models\Role::create([
            'name'       => 'test_payment_role',
            'label'      => 'Test Payment Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::PAYMENT_UPDATE->value);
        $staff->assignRole($role);

        expect($this->policy->refund($staff->fresh(), $this->payment))->toBeTrue();
    });

    it('denies refund without permission', function (): void {
        $staff = Staff::factory()->create();

        expect($this->policy->refund($staff, $this->payment))->toBeFalse();
    });

    // ─── deliver ────────────────────────────────────────────────────────────

    it('allows deliver with update permission', function (): void {
        $staff = Staff::factory()->create();
        $role  = Spatie\Permission\Models\Role::create([
            'name'       => 'test_payment_role',
            'label'      => 'Test Payment Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::PAYMENT_UPDATE->value);
        $staff->assignRole($role);

        expect($this->policy->deliver($staff->fresh(), $this->payment))->toBeTrue();
    });

    it('denies deliver without permission', function (): void {
        $staff = Staff::factory()->create();

        expect($this->policy->deliver($staff, $this->payment))->toBeFalse();
    });

    // ─── reverse ────────────────────────────────────────────────────────────

    it('allows reverse with delete permission', function (): void {
        $staff = Staff::factory()->create();
        $role  = Spatie\Permission\Models\Role::create([
            'name'       => 'test_payment_role',
            'label'      => 'Test Payment Role',
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo(PermissionEnum::PAYMENT_DELETE->value);
        $staff->assignRole($role);

        expect($this->policy->reverse($staff->fresh(), $this->payment))->toBeTrue();
    });

    it('denies reverse without permission', function (): void {
        $staff = Staff::factory()->create();

        expect($this->policy->reverse($staff, $this->payment))->toBeFalse();
    });
});
