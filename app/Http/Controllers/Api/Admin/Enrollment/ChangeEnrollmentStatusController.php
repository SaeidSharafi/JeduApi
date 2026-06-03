<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Enrollment;

use App\Actions\Admin\Enrollment\ChangeEnrollmentStatusAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Enrollment\EnrollmentData;
use App\Data\Admin\Enrollment\EnrollmentStatusChangeData;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Enrollment
 *
 * @subgroup Enrollment Status
 *
 * @authenticated
 */
final class ChangeEnrollmentStatusController extends Controller
{
    /**
     * Change enrollment status.
     *
     * This endpoint allows authorized staff to change the status of an enrollment.
     *
     * @responseFile resources/responses/admin/enrollment/show.json
     */
    public function __invoke(EnrollmentStatusChangeData $data, Enrollment $enrollment, ChangeEnrollmentStatusAction $action): ApiResponseInterface
    {
        Gate::authorize('changeStatus', $enrollment);

        $updated = $action->handle($enrollment, $data);
        $updated->load([
            'order.items.vendor',
            'order.payments',
            'customer',
            'productDeliveryOption',
            'orderItem.vendor',
        ]);

        return response()->success(
            data: EnrollmentData::from($updated),
            message: __('messages.enrollment.status_changed_successfully')
        );
    }
}
