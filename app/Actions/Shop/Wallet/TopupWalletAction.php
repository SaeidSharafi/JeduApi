<?php

declare(strict_types=1);

namespace App\Actions\Shop\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Validation\ValidationException;

final readonly class TopupWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransaction,
    ) {}

    /**
     * Credit wallet from a completed topup payment.
     */
    public function handle(Payment $payment): void
    {
        if ($payment->purpose !== PaymentPurposeEnum::WALLET_TOPUP) {
            throw ValidationException::withMessages([
                'payment' => __('messages.wallet.not_topup_payment', ['uuid' => $payment->uuid]),
            ]);
        }

        if ($payment->status !== PaymentStatusEnum::COMPLETED) {
            throw ValidationException::withMessages([
                'payment' => __('messages.wallet.not_completed', ['uuid' => $payment->uuid]),
            ]);
        }

        Wallet::firstOrCreate(['user_id' => $payment->customer_id]);

        $transactionData = new RecordTransactionData(
            user_id: $payment->customer_id,
            type: TransactionTypeEnum::DEPOSIT,
            amount: $payment->amount,
            source_type: TransactionSourceEnum::DEPOSIT,
            source_id: $payment->id,
            description: __('messages.wallet.topup_via', ['method' => $payment->method->value]),
            metadata: [
                'payment_uuid' => $payment->uuid,
                'method'       => $payment->method->value,
            ],
            idempotency_key: "wallet-topup:{$payment->id}",
        );

        $this->recordTransaction->execute($transactionData);
    }
}
