<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Payment\GetNextPaymentDetailsAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Exception;
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
     * @responseFile responses/order/next-payment-details.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function __invoke(Order $order, GetNextPaymentDetailsAction $action)
    {
        Gate::authorize('viewAny', Order::class);
        try {
            // The action will return the DTO or throw an exception.
            return response()->success($action->handle($order));
        } catch (Exception $e) {
            // Return a 422 Unprocessable Entity status if payment is not possible.
            return response()->validationErrors([$e->getMessage()], 422);
        }
    }
}
