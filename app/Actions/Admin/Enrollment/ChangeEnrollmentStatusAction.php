<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Data\Admin\Enrollment\EnrollmentStatusChangeData;
use App\Enums\EnrollmentStatusEnum;
use App\Models\Enrollment;
use App\Services\Provisioning\ProvisioningAttemptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ChangeEnrollmentStatusAction
{
    private const array ALLOWED_TRANSITIONS
        = [
            EnrollmentStatusEnum::AWAITING_PAYMENT->value => [
                EnrollmentStatusEnum::PENDING_PROVISIONING, EnrollmentStatusEnum::CANCELLED,
            ],
            EnrollmentStatusEnum::PENDING_PROVISIONING->value => [
                EnrollmentStatusEnum::ACTIVE, EnrollmentStatusEnum::PROVISIONING_FAILED,
                EnrollmentStatusEnum::CANCELLED,
            ],
            EnrollmentStatusEnum::ACTIVE->value => [
                EnrollmentStatusEnum::SUSPENDED, EnrollmentStatusEnum::EXPIRED, EnrollmentStatusEnum::CANCELLED,
            ],
            EnrollmentStatusEnum::SUSPENDED->value => [
                EnrollmentStatusEnum::ACTIVE, EnrollmentStatusEnum::CANCELLED,
            ],
            EnrollmentStatusEnum::PROVISIONING_FAILED->value => [
                EnrollmentStatusEnum::PENDING_PROVISIONING, EnrollmentStatusEnum::CANCELLED,
            ],
            EnrollmentStatusEnum::EXPIRED->value   => [],
            EnrollmentStatusEnum::CANCELLED->value => [],
        ];

    public function __construct(private ProvisioningAttemptService $attempts) {}

    /**
     * Execute the action.
     */
    public function handle(Enrollment $enrollment, EnrollmentStatusChangeData $data): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $data): Enrollment {
            $newStatus = EnrollmentStatusEnum::from($data->new_status);
            $this->validateTransition($enrollment->enrollment_status, $newStatus);
            if ($newStatus === EnrollmentStatusEnum::ACTIVE && ! $this->canActivateEnrollment($enrollment)) {
                throw ValidationException::withMessages([
                    'enrollment_status' => 'Enrollment cannot be activated before provisioning is healthy.',
                ]);
            }

            $enrollment->update([
                'enrollment_status' => $newStatus,
            ]);
            if ($newStatus !== EnrollmentStatusEnum::PENDING_PROVISIONING) {
                $this->attempts->recordAccessReconciliation(
                    $enrollment,
                    ['reason' => "Enrollment status changed to {$newStatus->value}.", 'status' => $newStatus->value],
                    auth('staff')->id(),
                );
            }

            if ($data->reason !== null && $data->reason !== '') {
                $timestamp   = now()->format('Y-m-d H:i:s');
                $staffId     = auth('staff')->id();
                $newNote     = "[{$timestamp}] Status changed to {$newStatus->value} by staff {$staffId}: {$data->reason}";
                $currentNote = $enrollment->notes ?? '';
                $updatedNote = $currentNote === '' ? $newNote : $currentNote.PHP_EOL.$newNote;

                $enrollment->update(['notes' => $updatedNote]);
            }

            return $enrollment->fresh();
        });
    }

    private function canActivateEnrollment(Enrollment $enrollment): bool
    {
        return ! $enrollment->hasRequiredProvisioningProviders()
            || $enrollment->hasHealthyProvisioningOutcomes();
    }

    /**
     * Validate the status transition.
     *
     * @throws ValidationException
     */
    private function validateTransition(EnrollmentStatusEnum $currentStatus, EnrollmentStatusEnum $newStatus): void
    {
        $allowedStatuses = self::ALLOWED_TRANSITIONS[$currentStatus->value];

        if (! in_array($newStatus, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'enrollment_status' => __('messages.enrollments.invalid_status_transition',
                    ['from' => $currentStatus->translate(), 'to' => $newStatus->translate()]),
            ]);
        }
    }
}
