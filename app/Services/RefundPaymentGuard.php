<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Exceptions\RefundValidationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;

/**
 * Shared payment resolution and cumulative refund-cap guard for the refund
 * actions, so a full-order refund and a Bundle Purchase refund enforce the
 * same gateway boundary.
 */
final class RefundPaymentGuard
{
    public function resolveCompletedPayment(Order $order): ?Payment
    {
        return $order->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->oldest()
            ->first();
    }

    public function assertRefundWithinPaymentLimit(Payment $payment, int $totalRefundAmount): void
    {
        $alreadyRefunded = Refund::query()
            ->where('payment_id', $payment->id)
            ->where('status', RefundStatusEnum::COMPLETED)
            ->sum('amount');

        if (($alreadyRefunded + $totalRefundAmount) > $payment->amount) {
            throw new RefundValidationException(__('validation.custom.refund.exceeds_payment_amount'));
        }
    }
}
