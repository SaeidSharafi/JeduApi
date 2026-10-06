<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Payment;
use App\Models\Staff;
use App\Policies\Admin\PaymentPolicy;
use Spatie\Permission\Models\Role;

describe('PaymentPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy  = new PaymentPolicy();
        $this->payment = Payment::factory()->create();
    });

    // ─── viewAny ────────────────────────────────────────────────────────────

    it('allows view any with permission', function (): void {
        $staff = Staff::factory()->create();
        $role  = Role::create([
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
        $role  = Role::create([
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
        $role  = Role::create([
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
        $role  = Role::create([
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
        $role  = Role::create([
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

describe('Payment permission boundaries', function (): void {
    it('allows payment mutations only with their matching staff permission', function (string $ability, PermissionEnum $permission): void {
        $staff = Staff::factory()->create();
        $role  = Role::create([
            'name'       => 'payment_mutation_'.$ability,
            'label'      => 'Payment mutation '.$ability,
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo($permission->value);
        $staff->assignRole($role);
        $payment = Payment::factory()->create();

        $policy  = new PaymentPolicy();
        $allowed = $ability === 'create'
            ? $policy->create($staff->fresh())
            : $policy->{$ability}($staff->fresh(), $payment);

        expect($allowed)->toBeTrue();
    })->with([
        'create' => ['create', PermissionEnum::PAYMENT_CREATE],
        'update' => ['update', PermissionEnum::PAYMENT_UPDATE],
        'delete' => ['delete', PermissionEnum::PAYMENT_DELETE],
    ]);

    it('denies payment mutations when staff has a different payment permission', function (string $ability, PermissionEnum $unrelatedPermission): void {
        $staff = Staff::factory()->create();
        $role  = Role::create([
            'name'       => 'unrelated_payment_'.$ability,
            'label'      => 'Unrelated payment '.$ability,
            'guard_name' => 'staff',
        ]);
        $role->givePermissionTo($unrelatedPermission->value);
        $staff->assignRole($role);
        $payment = Payment::factory()->create();

        $policy  = new PaymentPolicy();
        $allowed = $ability === 'create'
            ? $policy->create($staff->fresh())
            : $policy->{$ability}($staff->fresh(), $payment);

        expect($allowed)->toBeFalse();
    })->with([
        'create with view permission'   => ['create', PermissionEnum::PAYMENT_VIEW],
        'update with delete permission' => ['update', PermissionEnum::PAYMENT_DELETE],
        'delete with update permission' => ['delete', PermissionEnum::PAYMENT_UPDATE],
    ]);
});
