<?php

declare(strict_types=1);

use App\Actions\Admin\Enrollment\ChangeEnrollmentStatusAction;
use App\Data\Admin\Enrollment\EnrollmentStatusChangeData;
use App\Enums\EnrollmentStatusEnum;
use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

describe('ChangeEnrollmentStatusAction', function (): void {
    beforeEach(function (): void {
        $this->action = new ChangeEnrollmentStatusAction();
    });

    it('changes enrollment status with valid transition', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::AWAITING_PAYMENT,
        ]);

        $data = EnrollmentStatusChangeData::from([
            'new_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value,
            'reason'     => null,
        ]);

        $result = $this->action->handle($enrollment, $data);

        expect($result->enrollment_status)->toBe(EnrollmentStatusEnum::PENDING_PROVISIONING);

        $this->assertDatabaseHas('enrollments', [
            'id'                => $enrollment->id,
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING->value,
        ]);
    });

    it('appends reason to notes when provided', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
            'notes'             => 'Existing notes',
        ]);

        $data = EnrollmentStatusChangeData::from([
            'new_status' => EnrollmentStatusEnum::SUSPENDED->value,
            'reason'     => 'Customer requested suspension',
        ]);

        $result = $this->action->handle($enrollment, $data);

        expect($result->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED)
            ->and($result->notes)->toContain('Existing notes')
            ->and($result->notes)->toContain('Status changed to suspended')
            ->and($result->notes)->toContain('Customer requested suspension');
    });

    it('throws validation exception for invalid transition', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::EXPIRED,
        ]);

        $data = EnrollmentStatusChangeData::from([
            'new_status' => EnrollmentStatusEnum::ACTIVE->value,
            'reason'     => null,
        ]);

        expect(fn () => $this->action->handle($enrollment, $data))
            ->toThrow(ValidationException::class, __('messages.enrollments.invalid_status_transition',
                ['from' => EnrollmentStatusEnum::EXPIRED->translate(), 'to' => EnrollmentStatusEnum::ACTIVE->translate()]));
    });

    it('handles empty reason without modifying notes', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
            'notes'             => 'Original notes',
        ]);

        $data = EnrollmentStatusChangeData::from([
            'new_status' => EnrollmentStatusEnum::ACTIVE->value,
            'reason'     => '',
        ]);

        $result = $this->action->handle($enrollment, $data);

        expect($result->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE)
            ->and($result->notes)->toBe('Original notes');
    });
});
