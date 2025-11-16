<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Actions\Admin\Payment\CreatePaymentAction;
use App\Actions\Admin\Payment\DeletePaymentAction;
use App\Actions\Admin\Payment\UpdatePaymentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentData;
use App\Data\Admin\Payment\PaymentUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Payments
 *
 * Handles payment management for orders.
 * This controller allows administrators to view, create, update, and delete payments associated with orders.
 *
 * @authenticated
 */
final class PaymentController extends Controller
{
    /**
     * Display a listing of the payments for a specific order.
     */
    public function index(Order $order): ApiResponseInterface
    {
        Gate::authorize('view', $order);
        $payments = $order->payments()
            ->latest()
            ->get();

        return response()->success(PaymentData::collect($payments));
    }

    /**
     * Store a newly created payment for an order.
     *
     * For single-step payments (Wallet, Bank Transfer), the payment is completed immediately.
     * For multi-step payments (Online Gateway), returns redirect information for customer.
     *
     * @responseFile 201 responses/admin/payment/process-result.json
     * @responseFile 403 responses/403.json
     */
    public function store(PaymentCreateData $data, Order $order, CreatePaymentAction $action): ApiResponseInterface
    {
        Gate::authorize('create', Order::class);

        /** @var \App\Models\Staff $admin */
        $admin = auth()->user();

        $result = $action->handle($order, $data, $admin);

        // Return different response based on whether redirect is required
        return response()->created([
            'payment'           => PaymentData::from($result->payment),
            'requires_redirect' => $result->requiresRedirect(),
            'redirect_url'      => $result->redirect_url,
            'redirect_data'     => $result->redirect_data,
            'redirect_method'   => $result->redirect_method,
        ]);
    }

    /**
     * Display the specified payment for an order.
     *
     *
     * @responseFile 200 responses/payment/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function show(Order $order, Payment $payment): ApiResponseInterface
    {
        Gate::authorize('view', $order);

        return response()->success(PaymentData::from($payment));
    }

    /**
     * Update the specified payment for an order.
     *
     *
     *
     * @responseFile 200 responses/payment/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function update(PaymentUpdateData $request, Order $order, Payment $payment, UpdatePaymentAction $action): ApiResponseInterface
    {
        Gate::authorize('update', $order);
        $payment = $action->handle($order, $payment, $request);

        return response()->success(PaymentData::from($payment));
    }

    /**
     * Remove the specified payment from an order.
     *
     *
     *
     * @response 204 scenario="successful deletion"
     *
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function destroy(Order $order, Payment $payment, DeletePaymentAction $action): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('delete', $order);
        $action->handle($order, $payment);

        return response()->noContentJson();
    }
}
