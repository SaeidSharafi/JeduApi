<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Shop\Wallet\TopupWalletAction;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Payment;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Log;

final readonly class UpdateStatusesAfterPaymentListener
{
    public function __construct(
        private OrderStatusService $orderStatusService,
        private TopupWalletAction $topupWalletAction,
    ) {}

    public function handle(PaymentCompletedEvent $event): void
    {
        $payment = Payment::with('order')->find($event->payment->id);

        if ($payment->purpose === PaymentPurposeEnum::WALLET_TOPUP) {
            $this->topupWalletAction->handle($payment);

            return;
        }
        if ($payment->purpose === PaymentPurposeEnum::ORDER) {
            $order = $payment->order;

            if (! $order) {
                Log::error('PaymentCompletedEvent for ORDER but payment has no order', [
                    'payment_id'   => $payment->id,
                    'payment_uuid' => $payment->uuid,
                ]);

                return;
            }

            $this->orderStatusService->handlePaymentCompletion($order->fresh());
        }
    }
}
