<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Actions\Admin\Enrollment\ManualProvisioningRecoveryAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\EnrollmentData;
use App\Data\Admin\Enrollment\ManualProvisioningResolutionData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;

final class ResolveProvisioningController extends Controller
{
    public function __invoke(ManualProvisioningResolutionData $data, Enrollment $enrollment, ManualProvisioningRecoveryAction $action): ApiResponseInterface
    {
        Gate::authorize('resolveProvisioning', $enrollment);
        $enrollment = $action->resolve($enrollment, $data, (int) auth('staff')->id());

        return apiResponse()->success(EnrollmentData::from($enrollment), 'Provisioning provider resolved.');
    }
}
