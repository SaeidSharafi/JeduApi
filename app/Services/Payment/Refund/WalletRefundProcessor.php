<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Contracts\Payment\RefundProcessorInterface;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Order;
use App\Models\Refund;

final readonly class WalletRefundProcessor implements RefundProcessorInterface
{
    public function __construct(
        private RecordWalletTransactionAction $recordWalletTransaction,
    ) {}

    public function process(Refund $refund, Order $order, int $amount): ?string
    {
        $this->recordWalletTransaction->execute(new RecordTransactionData(
            user_id: $order->customer_id,
            amount: $amount,
            type: TransactionTypeEnum::REFUND,
            source_type: TransactionSourceEnum::ORDER,
            source_id: $refund->id,
            description: "Refund for order #{$order->id}",
        ));

        return null;
    }
}
