<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use App\Data\Admin\Wallet\WalletTransactionData;
use App\Data\Shop\Order\OrderData;
use App\Enums\Payment\PaymentTypeEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class PaymentCompletionResponseData extends Data
{
    public function __construct(
        #[WithCast(EnumCast::class)]
        public PaymentTypeEnum $payment_type,
        public PaymentData $payment,
        public ?OrderData $order = null,
        public ?WalletTransactionData $wallet_transaction = null,
        public ?array $wallet_balance = null,
    ) {}

    /**
     * Create response for completed order payment.
     */
    public static function forOrder(
        \App\Models\Payment $payment,
        \App\Models\Order $order
    ): self {
        return new self(
            payment_type: PaymentTypeEnum::ORDER,
            payment: PaymentData::from($payment),
            order: OrderData::from($order),
            wallet_transaction: null,
            wallet_balance: null,
        );
    }

    /**
     * Create response for completed wallet topup.
     */
    public static function forWalletTopup(
        \App\Models\Payment $payment,
        \App\Models\WalletTransaction $transaction
    ): self {
        $wallet = $transaction->wallet;

        return new self(
            payment_type: PaymentTypeEnum::WALLET_TOPUP,
            payment: PaymentData::from($payment),
            order: null,
            wallet_transaction: WalletTransactionData::from($transaction),
            wallet_balance: [
                'balance'      => $wallet->balance,
                'gift_balance' => $wallet->gift_balance,
            ],
        );
    }
}
