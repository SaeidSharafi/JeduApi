<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreatePaymentAction
{
    public function __construct(
        private PaymentProcessorFactory $processorFactory,
    ) {}

    /**
     * Initiate a payment for an order.
     *
     * Returns a PaymentProcessResultData which may contain:
     * - For single-step payments: completed payment with no redirect
     * - For multi-step payments: pending payment with redirect URL
     */
    public function handle(Order $order, PaymentCreateData $data, Staff $admin): ?PaymentProcessResultData
    {
        return DB::transaction(function () use ($order, $data, $admin): ?PaymentProcessResultData {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            // Check if order is free
            if ($order->grand_total <= 0) {
                if ($order->payments()->where('status', 'completed')->exists()) {
                    // Return already completed free payment
                    $existingPayment = $order->payments()->where('status', 'completed')->first();

                    return PaymentProcessResultData::completed($existingPayment);
                }

                return $this->createFreeOrderPayment($order, $data, $admin);
            }

            // Validate order state
            $this->validateOrderState($order);

            $amount = $this->calculateRequiredPayment($order);

            $processor = $this->processorFactory->make(PaymentMethodEnum::from($data->method));

            return $processor->process($order, $data, $admin, $amount);
        });
    }

    private function createFreeOrderPayment(Order $order, PaymentCreateData $data, Staff $admin): PaymentProcessResultData
    {
        $payment = Payment::create([
            'order_id'    => $order->id,
            'customer_id' => $order->customer_id,
            'amount'      => 0,
            'method'      => PaymentMethodEnum::NO_PAYMENT->value,
            'status'      => PaymentStatusEnum::COMPLETED->value,
            'created_by'  => $admin->id,
            'admin_notes' => $data->admin_notes ?? 'Free order automatically completed.',
        ]);

        PaymentCompletedEvent::dispatch($payment);

        return PaymentProcessResultData::completed($payment);
    }

    private function validateOrderState(Order $order): void
    {
        if ($order->balance_due <= 0) {
            throw ValidationException::withMessages([
                'payment' => __('messages.order.already_fully_paid', ['order_id' => $order->increment_id]),
            ]);
        }

        if ($order->payments()->where('status', PaymentStatusEnum::PENDING)->exists()) {
            throw ValidationException::withMessages([
                'payment' => __('messages.order.payment_already_pending', ['order_id' => $order->increment_id]),
            ]);
        }
    }

    private function calculateRequiredPayment(Order $order): int
    {
        $hasCompletedPayments = $order->payments()->where('status', 'completed')->exists();

        if (! $hasCompletedPayments) {
            // First payment: sum of all order items
            return $order->items->sum('total');
        }

        // Subsequent payments: remaining balance
        return $order->balance_due;
    }
}
