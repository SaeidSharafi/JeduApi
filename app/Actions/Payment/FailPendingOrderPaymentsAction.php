<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Close every open payment attempt of an order that just reached a terminal
 * state, so a cancelled/completed order never shows a pending payment.
 *
 * Called synchronously after the order transaction commits, never inside it:
 * the order transaction holds the order row, while verification locks
 * payment -> order. Taking payment locks inside the order transaction would
 * invert that order and risk a deadlock.
 */
final readonly class FailPendingOrderPaymentsAction
{
    public function __construct(
        private FailPaymentAttemptAction $failPaymentAttempt,
    ) {}

    public function handle(int $orderId): void
    {
        /** @var list<int|string> $paymentIds */
        $paymentIds = Payment::query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatusEnum::PENDING)
            ->pluck('id')
            ->all();

        foreach ($paymentIds as $paymentId) {
            try {
                $this->failPaymentAttempt->handle(
                    paymentId: (int) $paymentId,
                    errorCode: PaymentTransactionErrorCodeEnum::ORDER_CLOSED->value,
                    errorMessage: 'The order was closed before the payment attempt resolved.',
                );
            } catch (Throwable $exception) {
                // The order is already closed; a failed cascade must not turn a
                // successful cancellation into an error. payments:expire-pending
                // is the safety net for this row.
                Log::error('Failed to close a pending payment attempt for a closed order', [
                    'order_id'   => $orderId,
                    'payment_id' => $paymentId,
                    'error'      => $exception->getMessage(),
                ]);
            }
        }
    }
}
