<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Actions\Payment\PreparePendingPaymentAction;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Models\Order;
use App\Models\Payment;
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
     * NOTE: In the current pre-payment model, PENDING orders have zero
     * completed payments, so balance_due === grand_total. The $amountToPay
     * parameter defaults to balance_due for forward-compatibility with future
     * installment / online rest-payment features. Callers SHOULD omit the
     * parameter to use the default; passing grand_total explicitly is safe
     * today but will break once partial payments exist.
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
        $payment = DB::transaction(function () use ($order, $paymentMethod, $amountToPay): Payment {

            $this->validateOrderEligibility($order);

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

            return app(PreparePendingPaymentAction::class)->handle(
                actor: Auth::guard('user')->user(),
                customerId: $order->customer_id,
                method: $paymentMethod,
                purpose: PaymentPurposeEnum::ORDER,
                amount: $amountToPay,
                order: $order,
            );
        });

        $processor = $this->processorFactory->make($paymentMethod);

        return $processor->process($payment);
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
