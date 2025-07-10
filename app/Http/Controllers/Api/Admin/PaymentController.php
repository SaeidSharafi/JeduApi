<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Payment\CreatePaymentAction;
use App\Actions\Payment\DeletePaymentAction;
use App\Actions\Payment\UpdatePaymentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentData;
use App\Data\Admin\Payment\PaymentUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;

/**
 * @group Admin - Payments
 *
 * Handles payment management for orders.
 * This controller allows administrators to view, create, update, and delete payments associated with orders.
 *
 * @authenticated
 */
class PaymentController extends Controller
{
    /**
     * Display a listing of the payments for a specific order.
     *
     * @param Order $order
     * @return ApiResponseInterface
     */
    public function index(Order $order): ApiResponseInterface
    {
        $payments = $order->payments()
            ->latest()
            ->get();

        return response()->success(PaymentData::collect($payments));
    }

    /**
     * Store a newly created payment for an order.
     *
     * @param PaymentCreateData $data
     * @param Order $order
     * @param CreatePaymentAction $action
     *
     * @return ApiResponseInterface
     *
     * @responseFile 201 responses/payment/show.json
     * @response 400 scenario="amount to pay is zero" {
     *    "message": "The amount to pay is zero.",
     *    "errors": null,
     *    "metadata" => []
     * }
     * @responseFile 403 responses/403.json
     */
    public function store(PaymentCreateData $data,Order $order, CreatePaymentAction $action): ApiResponseInterface
    {
        $payment = $action->handle($order, $data, auth()->user());
        if (!$payment){
            return response()->error(__('messages.order.amount_to_pay_is_zero'));
        }
        return response()->created(PaymentData::from($payment));
    }

    /**
     * Display the specified payment for an order.
     *
     * @param Order $order
     * @param Payment $payment
     * @return ApiResponseInterface
     *
     * @responseFile 200 responses/payment/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function show(Order $order,Payment $payment): ApiResponseInterface
    {
        return response()->success(PaymentData::from($payment));
    }

    /**
     * Update the specified payment for an order.
     *
     * @param PaymentUpdateData $request
     * @param Order $order
     * @param Payment $payment
     * @param UpdatePaymentAction $action
     *
     * @return ApiResponseInterface
     *
     * @responseFile 200 responses/payment/show.json
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function update(PaymentUpdateData $request,Order $order,Payment $payment, UpdatePaymentAction $action): ApiResponseInterface
    {
        $payment = $action->handle($order, $payment, $request);
        return response()->success(PaymentData::from($payment));
    }

    /**
     * Remove the specified payment from an order.
     *
     * @param Order $order
     * @param Payment $payment
     * @param DeletePaymentAction $action
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @response 204 scenario="successful deletion"
     * @responseFile 404 responses/404.json
     * @responseFile 403 responses/403.json
     */
    public function destroy(Order $order,Payment $payment, DeletePaymentAction $action): \Illuminate\Http\JsonResponse
    {
        $action->handle($order, $payment);
        return response()->noContentJson();
    }
}
