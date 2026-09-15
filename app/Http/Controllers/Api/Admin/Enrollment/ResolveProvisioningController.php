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

/**
 * @group Admin - Enrollment
 *
 * @subgroup Enrollment Provisioning
 *
 * APIs for manually resolving failed enrollment provisioning.
 *
 * @authenticated
 */
final class ResolveProvisioningController extends Controller
{
    /**
     * Resolve a failed provisioning provider.
     *
     * Allows authorized staff to provide the external references needed to resolve a failed provisioning provider.
     *
     * @responseFile 200 resources/responses/admin/enrollment/show.json
     * @responseFile 404 resources/responses/404.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(ManualProvisioningResolutionData $data, Enrollment $enrollment, ManualProvisioningRecoveryAction $action): ApiResponseInterface
    {
        Gate::authorize('resolveProvisioning', $enrollment);
        $enrollment = $action->resolve($enrollment, $data, (int) auth('staff')->id());

        return apiResponse()->success(EnrollmentData::from($enrollment), 'Provisioning provider resolved.');
    }
}
