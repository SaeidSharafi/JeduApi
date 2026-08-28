<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Data\Admin\Enrollment\EnrollmentUpdateData;
use App\Models\Enrollment;
use App\Services\Provisioning\ProvisioningAttemptService;

final readonly class UpdateEnrollmentAction
{
    public function __construct(private ProvisioningAttemptService $attempts) {}

    /**
     * Execute the action.
     */
    public function handle(Enrollment $enrollment, EnrollmentUpdateData $data): Enrollment
    {
        $enrollment->update([
            'access_start_date' => $data->access_start_date,
            'access_end_date'   => $data->access_end_date,
            'notes'             => $data->notes,
        ]);

        if ($data->reason !== null && $data->reason !== '') {
            $timestamp = now()->format('Y-m-d H:i:s');
            $staffId   = auth('staff')->id();
            $auditNote = "[{$timestamp}] Access dates changed by staff {$staffId}: {$data->reason}";
            $enrollment->update(['notes' => mb_trim(($enrollment->notes ?? '').PHP_EOL.$auditNote)]);
        }

        if ($enrollment->wasChanged(['access_start_date', 'access_end_date'])) {
            $this->attempts->recordAccessReconciliation(
                $enrollment,
                ['reason'               => $data->reason ?? 'Enrollment access dates changed.',
                    'status'            => $enrollment->enrollment_status->value,
                    'access_start_date' => $data->access_start_date, 'access_end_date' => $data->access_end_date,
                ],
                auth('staff')->id(),
            );
        }

        return $enrollment->fresh();
    }
}
