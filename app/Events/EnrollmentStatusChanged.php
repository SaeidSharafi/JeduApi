<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\EnrollmentStatusEnum;
use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EnrollmentStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Enrollment  $enrollment  The enrollment that changed
     * @param  EnrollmentStatusEnum|null  $oldStatus  The previous status (null if newly created)
     * @param  EnrollmentStatusEnum  $newStatus  The new status
     */
    public function __construct(
        public Enrollment $enrollment,
        public ?EnrollmentStatusEnum $oldStatus,
        public EnrollmentStatusEnum $newStatus,
    ) {}
}
