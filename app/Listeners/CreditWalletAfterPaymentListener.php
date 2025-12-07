<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Shop\Wallet\TopupWalletAction;
use App\Enums\Payment\PaymentTypeEnum;
use App\Events\PaymentCompletedEvent;
use Exception;
use Illuminate\Support\Facades\Log;

final readonly class CreditWalletAfterPaymentListener
{
    public function __construct(
        private TopupWalletAction $topupWalletAction
    ) {}

    public function handle(PaymentCompletedEvent $event): void
    {
        $payment = $event->payment;

        // Only process wallet topup payments
        if ($payment->payment_type !== PaymentTypeEnum::WALLET_TOPUP) {
            return;
        }

        try {
            $this->topupWalletAction->handle($payment);

            Log::info('Wallet credited after payment completion', [
                'payment_id'  => $payment->id,
                'customer_id' => $payment->customer_id,
                'amount'      => $payment->amount,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to credit wallet after payment', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger queue retry
        }
    }
}
