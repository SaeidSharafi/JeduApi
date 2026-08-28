<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Enums\EnrollmentStatusEnum;
use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

final readonly class DeleteEnrollmentAction
{
    /**
     * Execute the action.
     *
     * @throws ValidationException
     */
    public function handle(Enrollment $enrollment): void
    {
        if ($enrollment->enrollment_status === EnrollmentStatusEnum::ACTIVE) {
            throw ValidationException::withMessages([
                'enrollment_status' => __(
                    'messages.enrollments.cannot_delete_enrollment',
                    ['status' => $enrollment->enrollment_status->translate()]
                ),
            ]);
        }

        $enrollment->delete();
    }
}
