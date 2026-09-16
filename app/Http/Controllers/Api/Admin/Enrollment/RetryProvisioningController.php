<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Actions\Admin\Enrollment\RetryProvisioningAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Enrollment
 *
 * @subgroup Enrollment Provisioning
 *
 * @authenticated
 */
final class RetryProvisioningController extends Controller
{
    /**
     * Retry enrollment provisioning.
     *
     * This endpoint allows authorized staff to retry the provisioning process for an enrollment, optionally
     * targeting a single provider.
     *
     * @urlParam provider string optional The provider to retry. Available values: `ims`, `moodle`, `spotplayer`, `skyroom`, `moodle_quiz`. Example: moodle
     *
     * @responseFile 200 resources/responses/admin/enrollment/retry-provisioning.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(Enrollment $enrollment, RetryProvisioningAction $action, ?string $provider = null): ApiResponseInterface
    {
        Gate::authorize('retryProvisioning', $enrollment);

        $result = $action->handle($enrollment, $provider);

        return apiResponse()->success(
            data: $result,
            message: __('messages.enrollment.provisioning_retry_dispatched')
        );
    }
}
