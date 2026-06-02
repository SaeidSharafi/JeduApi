<?php

declare(strict_types=1);

use App\Actions\Admin\Enrollment\DeleteEnrollmentAction;
use App\Enums\EnrollmentStatusEnum;
use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

describe('DeleteEnrollmentAction', function (): void {
    beforeEach(function (): void {
        $this->action = new DeleteEnrollmentAction();
    });

    it('deletes enrollment with cancelled status', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
        ]);

        $enrollmentId = $enrollment->id;

        $this->action->handle($enrollment);

        $this->assertDatabaseMissing('enrollments', [
            'id' => $enrollmentId,
        ]);
    });

    it('throws exception when deleting active enrollment', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        ]);

        expect(fn () => $this->action->handle($enrollment))
            ->toThrow(ValidationException::class, __(
                'messages.enrollments.cannot_delete_enrollment',
                ['status' => EnrollmentStatusEnum::ACTIVE->translate()]
            ));
    });

    it('throws exception when deleting pending provisioning enrollment', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
        ]);

        expect(fn () => $this->action->handle($enrollment))
            ->toThrow(ValidationException::class,  __(
                'messages.enrollments.cannot_delete_enrollment',
                ['status' => EnrollmentStatusEnum::PENDING_PROVISIONING->translate()]
            ));
    });

    it('allows deletion of expired enrollment', function (): void {
        $enrollment = Enrollment::factory()->create([
            'enrollment_status' => EnrollmentStatusEnum::EXPIRED,
        ]);

        $enrollmentId = $enrollment->id;

        $this->action->handle($enrollment);

        $this->assertDatabaseMissing('enrollments', [
            'id' => $enrollmentId,
        ]);
    });
});
