<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Discounts\RewardIntentData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Services\Discounts\Configs\AddGiftCreditConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('add_gift_credit')]
final class AddGiftCreditAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return AddGiftCreditConfigData::class;
    }

    /**
     * Records the intent to grant the customer gift credit. Pricing must stay
     * free of side effects, so no ledger row is written here: the intent is
     * executed by GrantOrderRewardsAction once the order reaches COMPLETED.
     * Only the expiry window is stored — the expiry date itself is computed at
     * grant time, so it counts from the day the gift is actually granted.
     */
    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (! $configuration instanceof AddGiftCreditConfigData) {
            return;
        }

        $customer = $context->customer;
        if (! $customer) {
            return;
        }

        $promotion = $context->evaluating_promotion;
        if ($promotion === null) {
            return;
        }

        $giftAmount = $this->calculateGiftAmount($context, $configuration);

        if ($giftAmount <= 0) {
            return;
        }

        $description = $configuration->description ?? __('wallet.promotion.gift_from_order', [
            'promotion' => $promotion->name,
        ]);

        $context->reward_intents[] = (new RewardIntentData(
            promotion_id: $promotion->id,
            promotion_name: $promotion->name,
            type: TransactionTypeEnum::GIFT,
            amount: $giftAmount,
            description: $description,
            configuration: $configuration->toArray(),
            expires_days: $configuration->expires_days,
        ))->toArray();
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
