<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\AddWalletCreditAction;
use App\Services\Discounts\Configs\AddWalletCreditConfigData;
use Illuminate\Support\Collection;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->user      = User::factory()->create();
    $this->promotion = DiscountPromotion::factory()->create([
        'name' => 'Test Promotion',
    ]);
    $this->action = app(AddWalletCreditAction::class);
});

/**
 * @param  Collection<int, CalculatedOrderItemData>|null  $items
 */
function walletRewardContext(User $user, DiscountPromotion $promotion, ?Collection $items = null): OrderContextData
{
    return new OrderContextData(
        customer: $user,
        items: $items ?? collect([]),
        subtotal_full_payment_items: 10000,
        subtotal_all_items: 10000,
        evaluating_promotion: $promotion,
    );
}

function walletRewardFullPaymentItem(ProductDeliveryOption $option, int $qty, int $price): CalculatedOrderItemData
{
    return new CalculatedOrderItemData(
        product_delivery_option: $option,
        qty: $qty,
        payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
        price: $price,
        total: $price * $qty,
    );
}

it('returns correct config class', function (): void {
    expect(AddWalletCreditAction::getConfigClass())->toBe(AddWalletCreditConfigData::class);
});

it('records a fixed amount wallet credit intent without touching the wallet', function (): void {
    $config = new AddWalletCreditConfigData(
        amount: 5000,
        per_item: false,
        description: 'Order completion bonus'
    );

    $context = walletRewardContext($this->user, $this->promotion);
    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1);

    $intent = $context->reward_intents[0];
    expect($intent['promotion_id'])->toBe($this->promotion->id)
        ->and($intent['promotion_name'])->toBe('Test Promotion')
        ->and($intent['type'])->toBe(TransactionTypeEnum::BONUS->value)
        ->and($intent['amount'])->toBe(5000)
        ->and($intent['description'])->toBe('Order completion bonus')
        ->and($intent['configuration']['amount'])->toBe(5000);
});

it('records a per-item wallet credit intent', function (): void {
    $config = new AddWalletCreditConfigData(
        amount: 1000,
        per_item: true,
        description: 'Per item bonus'
    );

    $deliveryOption = ProductDeliveryOption::factory()->create();

    $context = walletRewardContext($this->user, $this->promotion, collect([
        walletRewardFullPaymentItem($deliveryOption, 2, 5000),
        walletRewardFullPaymentItem($deliveryOption, 3, 3000),
    ]));

    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1)
        ->and($context->reward_intents[0]['amount'])->toBe(5000); // (2 + 3) * 1000
});

it('excludes prepayment items from per-item calculation', function (): void {
    $config = new AddWalletCreditConfigData(
        amount: 1000,
        per_item: true,
        description: 'Per item bonus'
    );

    $deliveryOption = ProductDeliveryOption::factory()->create();

    $context = walletRewardContext($this->user, $this->promotion, collect([
        walletRewardFullPaymentItem($deliveryOption, 2, 5000),
        new CalculatedOrderItemData(
            product_delivery_option: $deliveryOption,
            qty: 3,
            payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT, // This must be excluded
            price: 3000,
            total: 9000,
        ),
    ]));

    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1)
        ->and($context->reward_intents[0]['amount'])->toBe(2000); // Only 2 items * 1000
});

it('records no intent for zero amount', function (): void {
    $config = new AddWalletCreditConfigData(
        amount: 1000,
        per_item: true,
        description: 'Per item bonus'
    );

    $context = walletRewardContext($this->user, $this->promotion);

    $this->action->apply($context, $config);

    expect($context->reward_intents)->toBeEmpty();
});

it('records the intent even when the customer has no wallet', function (): void {
    // Wallet ownership is an execution concern: it must not decide eligibility.
    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();
    $userWithoutWallet->refresh();

    $config = new AddWalletCreditConfigData(
        amount: 5000,
        per_item: false,
        description: 'Order completion bonus'
    );

    $context = walletRewardContext($userWithoutWallet, $this->promotion);
    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1)
        ->and($context->reward_intents[0]['amount'])->toBe(5000);
});

it('uses localized description when none provided', function (): void {
    $config = new AddWalletCreditConfigData(
        amount: 5000,
        per_item: false,
        description: null
    );

    $context = walletRewardContext($this->user, $this->promotion);
    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1)
        ->and($context->reward_intents[0]['description'])->toContain('Test Promotion');
});

it('records no intent for invalid configuration type', function (): void {
    $invalidConfig = new class extends Spatie\LaravelData\Data
    {
        public function toArray(): array
        {
            return [];
        }
    };

    $context = walletRewardContext($this->user, $this->promotion);
    $this->action->apply($context, $invalidConfig);

    expect($context->reward_intents)->toBeEmpty();
});
