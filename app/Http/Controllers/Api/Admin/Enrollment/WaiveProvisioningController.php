<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Actions\Admin\Enrollment\ManualProvisioningRecoveryAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\EnrollmentData;
use App\Data\Admin\Enrollment\ManualProvisioningWaiverData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Enrollment
 *
 * @subgroup Enrollment Provisioning
 *
 * APIs for waiving failed enrollment provisioning.
 *
 * @authenticated
 */
final class WaiveProvisioningController extends Controller
{
    /**
     * Waive a failed provisioning provider.
     *
     * Allows authorized staff to permanently waive a provisioning provider for an enrollment, skipping it entirely.
     *
     * @responseFile 200 resources/responses/admin/enrollment/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(ManualProvisioningWaiverData $data, Enrollment $enrollment, ManualProvisioningRecoveryAction $action): ApiResponseInterface
    {
        Gate::authorize('waiveProvisioning', $enrollment);
        $enrollment = $action->waive($enrollment, $data, (int) auth('staff')->id());

        return apiResponse()->success(EnrollmentData::from($enrollment), 'Provisioning provider waived.');
    }
}
