<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Actions\Admin\Enrollment\RetryProvisioningAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;

/**
 * @group Enrollment Management
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
     * This endpoint allows authorized staff to retry the provisioning process for an enrollment.
     *
     * @response 200
     */
    public function __invoke(Enrollment $enrollment, RetryProvisioningAction $action): ApiResponseInterface
    {
        Gate::authorize('retryProvisioning', $enrollment);

        $result = $action->handle($enrollment);

        return response()->success(
            data: $result,
            message: __('messages.enrollment.provisioning_retry_dispatched')
        );
    }
}
