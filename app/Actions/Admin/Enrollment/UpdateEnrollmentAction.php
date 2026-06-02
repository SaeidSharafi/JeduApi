<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Data\Admin\Enrollment\EnrollmentUpdateData;
use App\Models\Enrollment;

final readonly class UpdateEnrollmentAction
{
    /**
     * Execute the action.
     */
    public function handle(Enrollment $enrollment, EnrollmentUpdateData $data): Enrollment
    {
        $enrollment->update([
            'access_start_date'      => $data->access_start_date,
            'access_end_date'        => $data->access_end_date,
            'external_enrollment_id' => $data->external_enrollment_id,
            'notes'                  => $data->notes,
        ]);

        return $enrollment->fresh();
    }
}
