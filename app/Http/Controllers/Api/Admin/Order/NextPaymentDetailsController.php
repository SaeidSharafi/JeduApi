<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Payment\GetNextPaymentDetailsAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Payments
 *
 * Handles the retrieval of next payment details for an order.
 */
final class NextPaymentDetailsController extends Controller
{
    /**
     * Display the next payment details for a given order.
     *
     * @responseFile resources/responses/admin/order/next-payment-details.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(Order $order, GetNextPaymentDetailsAction $action): \App\Contracts\ApiResponseInterface
    {
        Gate::authorize('viewAny', Order::class);

        return apiResponse()->success($action->handle($order));

    }
}
