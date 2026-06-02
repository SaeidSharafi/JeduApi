<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Enrollment;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

describe('ChangeEnrollmentStatusController', function (): void {
    it('can change enrollment status with valid transition', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_UPDATE->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollment.change-status', ['enrollment' => $enrollment->id]), [
            'new_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value,
            'reason'     => 'Payment received',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.enrollment_status.value', EnrollmentStatusEnum::PENDING_PROVISIONING->value);

        $this->assertDatabaseHas('enrollments', [
            'id'                => $enrollment->id,
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value,
        ]);

        $enrollment->refresh();
        expect($enrollment->notes)->toContain('Payment received');
    });

    it('appends reason to existing notes', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_UPDATE->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
            'notes'             => 'Initial note',
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollment.change-status', ['enrollment' => $enrollment->id]), [
            'new_status' => EnrollmentStatusEnum::SUSPENDED->value,
            'reason'     => 'Non-payment',
        ]);

        $response->assertOk();

        $enrollment->refresh();
        expect($enrollment->notes)
            ->toContain('Initial note')
            ->toContain('Status changed to suspended')
            ->toContain('Non-payment');
    });

    it('returns 422 for invalid status transition', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_UPDATE->value]);

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::EXPIRED,
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollment.change-status', ['enrollment' => $enrollment->id]), [
            'new_status' => EnrollmentStatusEnum::ACTIVE->value,
            'reason'     => null,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['enrollment_status']);
    });

    it('validates required new_status field', function (): void {
        $this->authorized_user([PermissionEnum::ENROLLMENT_UPDATE->value]);

        $enrollment = Enrollment::factory()->create();

        $response = $this->postJson(route('api.v1.admin.enrollment.change-status', ['enrollment' => $enrollment->id]), [
            'reason' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_status']);
    });

    it('cannot change status without permissions', function (): void {
        $this->unauthorized_user();

        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
        ]);

        $response = $this->postJson(route('api.v1.admin.enrollment.change-status', ['enrollment' => $enrollment->id]), [
            'new_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value,
        ]);

        $response->assertForbidden();
    });
});
