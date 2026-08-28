<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Actions\Admin\Enrollment\ManualProvisioningRecoveryAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\EnrollmentData;
use App\Data\Admin\Enrollment\ProvisioningPlanApplyData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;

final class ProvisioningPlanController extends Controller
{
    public function preview(Enrollment $enrollment, ManualProvisioningRecoveryAction $action): ApiResponseInterface
    {
        Gate::authorize('resolveProvisioning', $enrollment);

        return apiResponse()->success($action->preview($enrollment));
    }

    public function apply(ProvisioningPlanApplyData $data, Enrollment $enrollment, ManualProvisioningRecoveryAction $action): ApiResponseInterface
    {
        Gate::authorize('resolveProvisioning', $enrollment);
        $enrollment = $action->apply($enrollment, $data->confirm, (int) auth('staff')->id());

        return apiResponse()->success(EnrollmentData::from($enrollment), 'Provisioning plan rebuilt.');
    }
}
