<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Payment\GetNextPaymentDetailsAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;

class NextPaymentDetailsController extends Controller
{
    public function __invoke(Order $order, GetNextPaymentDetailsAction $action)
    {
        Gate::authorize('viewAny', Order::class);
        try {
            // The action will return the DTO or throw an exception.
            return response()->success($action->handle($order));
        } catch (\Exception $e) {
            // Return a 422 Unprocessable Entity status if payment is not possible.
            return response()->validationErrors([$e->getMessage()], 422);
        }
    }
}
