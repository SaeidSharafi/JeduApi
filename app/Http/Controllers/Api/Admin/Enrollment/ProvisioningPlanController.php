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

/**
 * @group Admin - Enrollment
 *
 * @subgroup Enrollment Provisioning Plan
 *
 * APIs for previewing and applying a rebuilt provisioning plan.
 *
 * @authenticated
 */
final class ProvisioningPlanController extends Controller
{
    /**
     * Preview the provisioning plan rebuild.
     *
     * Returns a preview of the rebuilt provisioning plan without applying it.
     *
     * @responseFile 200 resources/responses/admin/enrollment/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     */
    public function preview(Enrollment $enrollment, ManualProvisioningRecoveryAction $action): ApiResponseInterface
    {
        Gate::authorize('resolveProvisioning', $enrollment);

        return apiResponse()->success($action->preview($enrollment));
    }

    /**
     * Apply the provisioning plan rebuild.
     *
     * Rebuilds the provisioning plan for an enrollment after explicit confirmation.
     *
     * @bodyParam confirm boolean required Set to true to confirm and apply the plan. Example: true
     *
     * @responseFile 200 resources/responses/admin/enrollment/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function apply(ProvisioningPlanApplyData $data, Enrollment $enrollment, ManualProvisioningRecoveryAction $action): ApiResponseInterface
    {
        Gate::authorize('resolveProvisioning', $enrollment);
        $enrollment = $action->apply($enrollment, $data->confirm, (int) auth('staff')->id());

        return apiResponse()->success(EnrollmentData::from($enrollment), 'Provisioning plan rebuilt.');
    }
}
