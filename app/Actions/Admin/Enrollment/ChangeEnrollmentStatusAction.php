<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Data\Admin\Enrollment\EnrollmentStatusChangeData;
use App\Enums\EnrollmentStatusEnum;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ChangeEnrollmentStatusAction
{
    private const array ALLOWED_TRANSITIONS
        = [
            EnrollmentStatusEnum::AWAITING_PAYMENT->value => [
                EnrollmentStatusEnum::PENDING_PROVISIONING, EnrollmentStatusEnum::CANCELLED
            ],
            EnrollmentStatusEnum::PENDING_PROVISIONING->value => [
                EnrollmentStatusEnum::ACTIVE, EnrollmentStatusEnum::PROVISIONING_FAILED, EnrollmentStatusEnum::CANCELLED
            ],
            EnrollmentStatusEnum::ACTIVE->value => [
                EnrollmentStatusEnum::SUSPENDED, EnrollmentStatusEnum::EXPIRED, EnrollmentStatusEnum::CANCELLED
            ],
            EnrollmentStatusEnum::SUSPENDED->value => [
                EnrollmentStatusEnum::ACTIVE, EnrollmentStatusEnum::CANCELLED
            ],
            EnrollmentStatusEnum::PROVISIONING_FAILED->value => [
                EnrollmentStatusEnum::PENDING_PROVISIONING, EnrollmentStatusEnum::CANCELLED
            ],
            EnrollmentStatusEnum::EXPIRED->value => [],
            EnrollmentStatusEnum::CANCELLED->value => [],
        ];

    /**
     * Execute the action.
     */
    public function handle(Enrollment $enrollment, EnrollmentStatusChangeData $data): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $data): Enrollment {
            $newStatus = EnrollmentStatusEnum::from($data->new_status);
            $this->validateTransition($enrollment->enrollment_status, $newStatus);

            $enrollment->update([
                'enrollment_status' => $newStatus,
            ]);

            if ($data->reason !== null && $data->reason !== '') {
                $timestamp = now()->format('Y-m-d H:i:s');
                $newNote = "[{$timestamp}] Status changed to {$newStatus->value}: {$data->reason}";
                $currentNote = $enrollment->notes ?? '';
                $updatedNote = $currentNote === '' ? $newNote : $currentNote.PHP_EOL.$newNote;

                $enrollment->update(['notes' => $updatedNote]);
            }

            return $enrollment->fresh();
        });
    }

    /**
     * Validate the status transition.
     *
     * @throws ValidationException
     */
    private function validateTransition(EnrollmentStatusEnum $currentStatus, EnrollmentStatusEnum $newStatus): void
    {
        $allowedStatuses = self::ALLOWED_TRANSITIONS[$currentStatus->value];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'enrollment_status' => __('messages.enrollments.invalid_status_transition',
                    ['from' => $currentStatus->translate(), 'to' => $newStatus->translate()]),
            ]);
        }
    }
}
