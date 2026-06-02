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
    private const array ALLOWED_TRANSITIONS = [
        'awaiting_payment'     => ['pending_provisioning', 'cancelled'],
        'pending_provisioning' => ['active', 'provisioning_failed', 'cancelled'],
        'active'               => ['suspended', 'expired', 'cancelled'],
        'suspended'            => ['active', 'cancelled'],
        'provisioning_failed'  => ['pending_provisioning', 'cancelled'],
        'expired'              => [],
        'cancelled'            => [],
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
                $timestamp   = now()->format('Y-m-d H:i:s');
                $newNote     = "[{$timestamp}] Status changed to {$newStatus->value}: {$data->reason}";
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
        $allowedStatuses = self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? [];

        if (! in_array($newStatus->value, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'enrollment_status' => sprintf(
                    'Cannot transition from %s to %s',
                    $currentStatus->value,
                    $newStatus->value
                ),
            ]);
        }
    }
}
