<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Actions\Payment\PreparePendingPaymentAction;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
    public function handle(Order $order, PaymentCreateData $data, Staff $admin): PaymentProcessResultData
    {
        return DB::transaction(function () use ($order, $data, $admin): PaymentProcessResultData {
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
            // validate Bank transfer data
            $this->validateBankTransferDetails($data);
            // Validate order state
            $this->validateOrderState($order);

            $amount = $this->calculateRequiredPayment($order);

            $processor = $this->processorFactory->make(PaymentMethodEnum::BANK_TRANSFER);

            $payment = app(PreparePendingPaymentAction::class)->handle(
                actor: $admin,
                customerId: $order->customer_id,
                method: PaymentMethodEnum::BANK_TRANSFER,
                purpose: PaymentPurposeEnum::ORDER,
                amount: $amount,
                order: $order,
                adminNotes: $data->admin_notes,
            );

            // Set payment data (e.g., bank transfer details) for validation
            if ($data->data !== null) {
                $payment->update(['data' => $data->data->toArray()]);
            }

            return $processor->process($payment);
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

    private function validateBankTransferDetails(PaymentCreateData $paymentData): void
    {
        $dataToValidate = [
            'data' => $paymentData->data?->toArray() ?? [],
        ];

        $rules = [
            'data.transaction_id'   => ['required', 'string', 'max:255'],
            'data.transaction_date' => ['required'],
            'data.sender_name' => ['required', 'string', 'max:255'],
            'data.notes'       => ['nullable', 'string', 'max:1000'],
        ];

        Validator::make($dataToValidate, $rules)->validate();
    }
}
