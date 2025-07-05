<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Payment\CreatePaymentAction;
use App\Actions\Payment\DeletePaymentAction;
use App\Actions\Payment\UpdatePaymentAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Payment\PaymentCreateData;
use App\Data\Payment\PaymentData;
use App\Data\Payment\PaymentUpdateData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index(Order $order): ApiResponseInterface
    {
        $payments = $order->payments()
            ->latest()
            ->get();

        return response()->success(PaymentData::collect($payments));
    }

    public function store(PaymentCreateData $data,Order $order, CreatePaymentAction $action): ApiResponseInterface
    {
        $payment = $action->handle($order, $data, auth()->user());
        if (!$payment){
            return response()->error(__('messages.order.amount_to_pay_is_zero'));
        }
        return response()->created(PaymentData::from($payment));
    }

    public function show(Order $order,Payment $payment): ApiResponseInterface
    {
        return response()->success(PaymentData::from($payment));
    }

    public function update(PaymentUpdateData $request,Order $order,Payment $payment, UpdatePaymentAction $action): ApiResponseInterface
    {
        $payment = $action->handle($order, $payment, $request);
        return response()->success(PaymentData::from($payment));
    }

    public function destroy(Order $order,Payment $payment, DeletePaymentAction $action): \Illuminate\Http\JsonResponse
    {
        $action->handle($order, $payment);
        return response()->noContentJson();
    }
}
