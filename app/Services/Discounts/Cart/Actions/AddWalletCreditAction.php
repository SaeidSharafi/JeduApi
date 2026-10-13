<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Actions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Data\Admin\Discounts\RewardIntentData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Services\Discounts\Configs\AddWalletCreditConfigData;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('add_wallet_credit')]
final class AddWalletCreditAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return AddWalletCreditConfigData::class;
    }

    /**
     * Records the intent to credit the customer's wallet. Pricing must stay free
     * of side effects, so no ledger row is written here: the intent is executed
     * by GrantOrderRewardsAction once the order reaches COMPLETED. Whether the
     * customer actually owns a wallet is an execution concern, not an
     * eligibility one.
     */
    public function apply(OrderContextData $context, Data $configuration): void
    {
        if (! $configuration instanceof AddWalletCreditConfigData) {
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

        $creditAmount = $this->calculateCreditAmount($context, $configuration);

        if ($creditAmount <= 0) {
            return;
        }

        $description = $configuration->description ?? __('wallet.promotion.credit_from_order', [
            'promotion' => $promotion->name,
        ]);

        $context->reward_intents[] = (new RewardIntentData(
            promotion_id: $promotion->id,
            promotion_name: $promotion->name,
            type: TransactionTypeEnum::BONUS,
            amount: $creditAmount,
            description: $description,
            configuration: $configuration->toArray(),
        ))->toArray();
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
        }

        // Fixed amount credit
        return $configuration->amount;

    }
}
