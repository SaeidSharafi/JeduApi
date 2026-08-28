<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Enrollment;
use App\Services\Enrollment\ProvisioningPlanResolver;
use Illuminate\Support\Str;

final readonly class EnrollmentObserver
{
    public function __construct(private ProvisioningPlanResolver $planResolver) {}

    public function creating(Enrollment $enrollment): void
    {
        $enrollment->uuid                = (string) Str::uuid7();
        $plan                            = $this->planResolver->resolve($enrollment->productDeliveryOption()->firstOrFail());
        $enrollment->provisioning_plan   = $plan;
        $enrollment->provisioning_status = $plan['status'];
    }
}
