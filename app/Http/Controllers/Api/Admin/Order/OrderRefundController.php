<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Refund\RefundOrderAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Refund\RefundData;
use App\Data\Admin\Refund\RefundOrderData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Refunds
 *
 * @subgroup Order Refunds
 *
 * APIs for refunding an entire order at once.
 */
final class OrderRefundController extends Controller
{
    /**
     * Refund the entire order.
     *
     * Creates refund records for all refundable items in the order.
     * For Digipay orders where partial refunds are not supported, this is the required path.
     *
     * @responseFile 201 resources/responses/admin/refund/index.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 422 resources/responses/admin/refund/store-422.json
     */
    public function store(RefundOrderData $data, Order $order, RefundOrderAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Refund::class);

        if ($data->skip_gateway) {
            Gate::authorize('skipGateway', Refund::class);
        }

        $refunds = $action->handle($order, $data);

        return response()->created(RefundData::collect($refunds));
    }
}
