<?php

declare(strict_types=1);

namespace App\Actions\Shop\Wallet;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Exception;
use InvalidArgumentException;

final readonly class TopupWalletAction
{
    public function __construct(
        private RecordWalletTransactionAction $recordTransactionAction
    ) {}

    /**
     * Deposit amount to user's wallet.
     *
     * @throws Exception
     */
    public function handle(Payment $completedPayment): WalletTransaction
    {
        if ($completedPayment->payment_type !== PaymentTypeEnum::WALLET_TOPUP) {
            throw new InvalidArgumentException(
                "Payment #{$completedPayment->id} is not a wallet topup (type: {$completedPayment->payment_type->value})."
            );
        }

        if ($completedPayment->status !== PaymentStatusEnum::COMPLETED) {
            throw new InvalidArgumentException(
                "Payment #{$completedPayment->id} must be completed to credit wallet (status: {$completedPayment->status->value})."
            );
        }

        if ($completedPayment->order_id !== null) {
            throw new InvalidArgumentException(
                "Payment #{$completedPayment->id} has an order_id. Use order payment processing instead."
            );
        }

        if (! $completedPayment->customer->wallet->isActive()) {
            throw new Exception(__('validation.custom.wallet_not_active'));
        }

        return $this->recordTransactionAction->execute(new RecordTransactionData(
            user_id: $completedPayment->customer_id,
            type: TransactionTypeEnum::DEPOSIT,
            amount: $completedPayment->amount,
            source_type: TransactionSourceEnum::PAYMENT,
            source_id: $completedPayment->id,
            description: "Wallet top-up via {$completedPayment->method->value}",
            metadata: [
                'payment_id'        => $completedPayment->id,
                'payment_uuid'      => $completedPayment->uuid,
                'payment_method'    => $completedPayment->method->value,
                'gateway_reference' => $completedPayment->last_gateway_reference,
                'topup_timestamp'   => now()->toIso8601String(),
                'ip_address'        => $completedPayment->ip_address,
                'user_agent'        => $completedPayment->user_agent,
            ],
        ));
    }
}
