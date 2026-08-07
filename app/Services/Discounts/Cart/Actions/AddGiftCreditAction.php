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
use App\Services\Discounts\Configs\AddGiftCreditConfigData;
use Exception;
use Log;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('add_gift_credit')]
final class AddGiftCreditAction implements DiscountActionContract
{
    public function __construct(
        private readonly RecordWalletTransactionAction $recordWalletTransactionAction
    ) {}

    public static function getConfigClass(): string
    {
        return AddGiftCreditConfigData::class;
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (! $configuration instanceof AddGiftCreditConfigData) {
            return;
        }

        // Get the customer from the context
        $customer = $context->customer;
        if (! $customer || ! $customer->wallet) {
            return;
        }

        // Calculate the gift credit amount
        $giftAmount = $this->calculateGiftAmount($context, $configuration);

        if ($giftAmount <= 0) {
            return;
        }

        // Calculate expiration date if specified
        $expiresAt = null;
        if ($configuration->expires_days !== null) {
            $expiresAt = now()->addDays($configuration->expires_days)->format('Y-m-d H:i:s');
        }

        // Prepare description
        $description = $configuration->description ?? __('wallet.promotion.gift_from_order', [
            'promotion' => $context->evaluating_promotion->name ?? __('wallet.promotion.discount'),
        ]);

        // Record gift transaction
        try {
            $transactionData = new RecordTransactionData(
                user_id: $customer->id,
                type: TransactionTypeEnum::GIFT,
                amount: $giftAmount,
                source_type: TransactionSourceEnum::PROMOTION,
                source_id: $context->evaluating_promotion?->id,
                description: $description,
                metadata: [
                    'order_id'       => $context->order_id ?? null,
                    'promotion_name' => $context->evaluating_promotion?->name,
                    'credit_type'    => 'gift',
                    'expires_days'   => $configuration->expires_days,
                    'configuration'  => $configuration->toArray(),
                ],
                expires_at: $expiresAt
            );

            $this->recordWalletTransactionAction->execute($transactionData);
        } catch (Exception $e) {
            // Log the error but don't break the order process
            Log::error('Failed to record gift credit from promotion', [
                'error'        => $e->getMessage(),
                'customer_id'  => $customer->id,
                'promotion_id' => $context->evaluating_promotion?->id,
                'amount'       => $giftAmount,
            ]);
        }
    }

    private function calculateGiftAmount(OrderContextData $context, AddGiftCreditConfigData $configuration): int
    {
        if ($configuration->per_item) {
            // Award gift credit per item (excluding prepayment items)
            $eligibleItemsCount = 0;
            foreach ($context->items as $item) {
                if ($item->payment_type !== OrderItemPaymentTypeEnum::PRE_PAYMENT) {
                    $eligibleItemsCount += $item->qty;
                }
            }

            return $configuration->amount * $eligibleItemsCount;
        }

        // Fixed amount gift credit
        return $configuration->amount;

    }
}
