<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Services\Discounts\Configs\AddWalletCreditConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('add_wallet_credit')]
final class AddWalletCreditAction implements DiscountActionContract
{
    public function __construct(
        private readonly RecordWalletTransactionAction $recordWalletTransactionAction
    ) {}

    public static function getConfigClass(): string
    {
        return AddWalletCreditConfigData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (!$configuration instanceof AddWalletCreditConfigData) {
            return;
        }

        // Get the customer from the context
        $customer = $context->customer;
        if (!$customer || !$customer->wallet) {
            return;
        }

        // Calculate the credit amount
        $creditAmount = $this->calculateCreditAmount($context, $configuration);

        if ($creditAmount <= 0) {
            return;
        }

        // Prepare description
        $description = $configuration->description ?? __('wallet.promotion.credit_from_order', [
            'promotion' => $context->evaluating_promotion?->name ?? __('wallet.promotion.discount')
        ]);

        // Record wallet transaction
        try {
            $transactionData = new RecordTransactionData(
                user_id: $customer->id,
                type: TransactionTypeEnum::BONUS,
                amount: $creditAmount,
                source_type: TransactionSourceEnum::PROMOTION,
                source_id: $context->evaluating_promotion?->id,
                description: $description,
                metadata: [
                    'order_id' => $context->order_id ?? null,
                    'promotion_name' => $context->evaluating_promotion?->name,
                    'credit_type' => 'regular',
                    'configuration' => $configuration->toArray()
                ]
            );

            $this->recordWalletTransactionAction->execute($transactionData);
        } catch (\Exception $e) {
            // Log the error but don't break the order process
            \Log::error('Failed to record wallet credit from promotion', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'promotion_id' => $context->evaluating_promotion?->id,
                'amount' => $creditAmount
            ]);
        }
    }

    private function calculateCreditAmount(OrderContextData $context, AddWalletCreditConfigData $configuration): int
    {
        if ($configuration->per_item) {
            // Award credit per item (excluding prepayment items)
            $eligibleItemsCount = 0;
            foreach ($context->items as $item) {
                if ($item->payment_type !== OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                    $eligibleItemsCount += $item->qty;
                }
            }
            return $configuration->amount * $eligibleItemsCount;
        } else {
            // Fixed amount credit
            return $configuration->amount;
        }
    }
}
