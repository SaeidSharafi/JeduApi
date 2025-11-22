<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Data\Admin\Payment\PaymentCreateData;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Services\Payment\PaymentProcessorFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryOrderPaymentAction
{
    public function __construct(
        private PaymentProcessorFactory $processorFactory,
    ) {}

    /**
     * Retry payment for a failed/pending order.
     *
     * This allows customers to attempt payment again on orders that:
     * - Are in PENDING status
     * - Have failed or pending payment attempts
     * - Still have outstanding balance
     *
     * @param  Order  $order  The order to retry payment for
     * @param  PaymentMethodEnum  $paymentMethod  The payment method to use for retry
     * @param  int|null  $amountToPay  Optional: Amount to pay (defaults to balance_due)
     * @return PaymentProcessResultData Result of payment processing (may include redirect)
     *
     * @throws ValidationException If order cannot be retried
     */
    public function handle(
        Order $order,
        PaymentMethodEnum $paymentMethod,
        ?int $amountToPay = null
    ): PaymentProcessResultData {
        return DB::transaction(function () use ($order, $paymentMethod, $amountToPay): PaymentProcessResultData {
            // Validate order is eligible for retry
            $this->validateOrderEligibility($order);

            // Use balance_due if amount not specified
            $amountToPay = $amountToPay ?? $order->balance_due;

            if ($amountToPay <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('validation.custom.checkout.no_outstanding_balance'),
                ]);
            }

            if ($amountToPay > $order->balance_due) {
                throw ValidationException::withMessages([
                    'amount' => __('validation.custom.checkout.payment_exceeds_balance_due', ['balance_due' => $order->balance_due]),
                ]);
            }

            // Build payment data
            $paymentData = new PaymentCreateData(
                method: $paymentMethod->value,
                status: 'pending', // Payment starts as pending
                data: null, // No additional data needed for retry
                admin_notes: 'Payment retry by customer',
            );

            // Get the payment processor and process payment
            $processor = $this->processorFactory->make($paymentMethod);

            $result = $processor->process(
                order: $order,
                paymentData: $paymentData,
                adminUser: Auth::guard('user')->user(),
                amountToPay: $amountToPay
            );

            return $result;
        });
    }

    /**
     * Validate that the order is eligible for payment retry.
     *
     * @throws ValidationException
     */
    private function validateOrderEligibility(Order $order): void
    {
        // Order must be in PENDING status
        if ($order->status !== OrderStatusEnum::PENDING) {
            throw ValidationException::withMessages([
                'order' => __('validation.custom.checkout.order_not_eligible_for_payment_retry', ['order_status' => $order->status->translate()]),
            ]);
        }

        // Order must have outstanding balance
        if ($order->balance_due <= 0) {
            throw ValidationException::withMessages([
                'order' => __('validation.custom.checkout.order_fully_paid'),
            ]);
        }
    }
}
