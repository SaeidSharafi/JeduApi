<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Refund\UpdateRefundStatusAction;
use App\Data\Admin\Refund\RefundData;
use App\Data\Admin\Refund\RefundStatusUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Refunds
 *
 * APIs for managing refunds in the admin panel.
 */
final class RefundUpdateStatusController extends Controller
{
    /**
     * Update the status of a refund.
     *
     * @responseFile 200 resources/responses/admin/refund/show.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/admin/refund/update-status-422.json
     */
    public function __invoke(RefundStatusUpdateData $data, Refund $refund, UpdateRefundStatusAction $action)
    {
        Gate::authorize('update-status', $refund);
        $refund = $action->handle($refund, $data);
        $refund->loadMissing('order');

        return apiResponse()->success(RefundData::from($refund));
    }
}
