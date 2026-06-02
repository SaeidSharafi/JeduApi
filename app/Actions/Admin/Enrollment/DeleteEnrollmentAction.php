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
        if (
            $enrollment->enrollment_status    === EnrollmentStatusEnum::ACTIVE
            || $enrollment->enrollment_status === EnrollmentStatusEnum::PENDING_PROVISIONING
        ) {
            throw ValidationException::withMessages([
                'enrollment_status' => sprintf(
                    'Cannot delete enrollment with status: %s',
                    $enrollment->enrollment_status->value
                ),
            ]);
        }

        $enrollment->delete();
    }
}
