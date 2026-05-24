<?php

declare(strict_types=1);

namespace App\Events;

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
     */
    public function __construct(
        public Enrollment $enrollment,
    ) {}
}
