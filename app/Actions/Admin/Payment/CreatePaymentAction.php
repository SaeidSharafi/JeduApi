<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreatePaymentAction
{
    /**
     * Creates a new Payment record for an Order.
     * The amount is NOT taken from user input; it is calculated based on the
     * payment_type ('full_payment' or 'pre_payment') stored on the order items.
     */
    public function handle(Order $order, PaymentCreateData $paymentData, Authenticatable|Staff $adminUser): ?Payment
    {
        return DB::transaction(function () use ($order, $paymentData, $adminUser): ?Payment {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->grand_total <= 0) {
                if ($order->payments()->where('status', 'completed')->exists()) {
                    // Already has a completion record, do nothing.
                    return null;
                }
                return $this->createPaymentRecordAndDispatchEvents(
                    order: $order,
                    adminUser: $adminUser,
                    amount: 0,
                    method: PaymentMethodEnum::NO_PAYMENT->value,
                    status: PaymentStatusEnum::COMPLETED->value,
                    adminNotes: $paymentData->admin_notes ?? 'Free order automatically completed.',
                    paymentMethodData: null
                );
            }

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

            $amountToPay = $this->calculateRequiredPayment($order);

            if ($amountToPay > 0) {
                $this->validateBankTransferDetails($paymentData);
            }

            return $this->createPaymentRecordAndDispatchEvents(
                order: $order,
                adminUser: $adminUser,
                amount: (int) round($amountToPay),
                method: $paymentData->method,
                status: $paymentData->status,
                adminNotes: $paymentData->admin_notes,
                paymentMethodData: $paymentData->data->toArray()
            );
        });
    }

    /**
     * Throws a ValidationException if required bank transfer details are missing.
     *
     * @throws ValidationException
     */
    private function validateBankTransferDetails(PaymentCreateData $paymentData): void
    {
        $dataToValidate = [
            'data' => $paymentData->data?->toArray() ?? [],
        ];

        $rules = [
            'data.transaction_id'   => ['required', 'string', 'max:255'],
            'data.transaction_date' => [
                'required', 'date:Y-m-d', Rule::date()->beforeOrEqual(today())
            ],
            'data.sender_name'      => ['required', 'string', 'max:255'],
            'data.notes'            => ['nullable', 'string', 'max:1000'],
        ];

        Validator::make($dataToValidate, $rules)->validate();

    }

    /**
     * Creates the payment record in the database and dispatches events.
     */
    private function createPaymentRecordAndDispatchEvents(
        Order $order,
        Authenticatable|Staff $adminUser,
        int $amount,
        string $method,
        string $status,
        ?string $adminNotes,
        ?array $paymentMethodData = null
    ): Payment {
        $payment = $order->payments()->create([
            'customer_id' => $order->customer_id,
            'created_by'  => $adminUser->id,
            'amount'      => $amount,
            'method'      => $method,
            'status'      => $status,
            'admin_notes' => $adminNotes,
            'data'        => $paymentMethodData
        ]);

        // CRITICAL: This is the trigger for fulfillment.
        // If the payment is marked as completed, tell the rest of the system.
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            // Your listeners for this event will handle activating enrollments,
            // updating order status, sending emails, etc.
            PaymentCompletedEvent::dispatch($payment);
        }

        return $payment;
    }

    /**
     * Calculates the intended initial payment based on the choices made during order creation.
     */
    private function calculateRequiredPayment(Order $order): float
    {
        $hasCompletedPayments = $order->payments()->where('status', 'completed')->exists();

        // SCENARIO 1: This is the very first payment for the order.
        if (!$hasCompletedPayments) {
            return $order->items->sum('total');
        }

        // SCENARIO 2: An initial payment was already made. The amount due now is simply the remaining balance.
        // The balance_due accessor is the perfect source of truth for this.
        return $order->balance_due;
    }
}
