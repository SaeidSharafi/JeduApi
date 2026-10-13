<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\AddGiftCreditAction;
use App\Services\Discounts\Configs\AddGiftCreditConfigData;
use Tests\Support\Traits\AuthTestTrait;

covers(AddGiftCreditAction::class);

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->user      = User::factory()->create();
    $this->promotion = DiscountPromotion::factory()->create([
        'name' => 'Test Promotion',
    ]);
    $this->action = app(AddGiftCreditAction::class);
});

it('returns correct config class', function (): void {
    expect(AddGiftCreditAction::getConfigClass())->toBe(AddGiftCreditConfigData::class);
});

it('records a fixed amount gift credit intent without an expiry window', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: null,
        description: 'Gift bonus'
    );

    $context = discountRewardContext($this->user, $this->promotion);
    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1);

    $intent = $context->reward_intents[0];
    expect($intent['promotion_id'])->toBe($this->promotion->id)
        ->and($intent['promotion_name'])->toBe('Test Promotion')
        ->and($intent['ledger_type'])->toBe(TransactionTypeEnum::GIFT->value)
        ->and($intent['amount'])->toBe(5000)
        ->and($intent['description'])->toBe('Gift bonus')
        // Only the window is stored; the expiry date is computed at grant time.
        ->and($intent['expires_days'])->toBeNull()
        ->and($intent)->not->toHaveKey('expires_at');
});

it('stores the expiry window rather than a computed expiry date', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: 30,
        description: 'Gift bonus'
    );

    $context = discountRewardContext($this->user, $this->promotion);
    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1)
        ->and($context->reward_intents[0]['expires_days'])->toBe(30)
        ->and($context->reward_intents[0]['configuration']['expires_days'])->toBe(30);
});

it('records a per-item gift credit intent', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 1000,
        per_item: true,
        expires_days: 15,
        description: 'Per item gift'
    );

    $deliveryOption = ProductDeliveryOption::factory()->create();

    $context = discountRewardContext($this->user, $this->promotion, collect([
        discountRewardFullPaymentItem($deliveryOption, 2, 5000),
        discountRewardFullPaymentItem($deliveryOption, 3, 3000),
    ]));

    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1)
        ->and($context->reward_intents[0]['amount'])->toBe(5000); // (2 + 3) * 1000
});

it('excludes prepayment items from per-item calculation', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 1000,
        per_item: true,
        expires_days: null,
        description: 'Per item gift'
    );

    $deliveryOption = ProductDeliveryOption::factory()->create();

    $context = discountRewardContext($this->user, $this->promotion, collect([
        discountRewardFullPaymentItem($deliveryOption, 2, 5000),
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
    $config = new AddGiftCreditConfigData(
        amount: 1000,
        per_item: true,
        expires_days: 7,
        description: 'Per item gift'
    );

    $context = discountRewardContext($this->user, $this->promotion);
    $this->action->apply($context, $config);

    expect($context->reward_intents)->toBeEmpty();
});

it('records the intent even when the customer has no wallet', function (): void {
    // Wallet ownership is an execution concern: it must not decide eligibility.
    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();
    $userWithoutWallet->refresh();

    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: null,
        description: 'Gift bonus'
    );

    $context = discountRewardContext($userWithoutWallet, $this->promotion);
    $this->action->apply($context, $config);

    expect($context->reward_intents)->toHaveCount(1)
        ->and($context->reward_intents[0]['amount'])->toBe(5000);
});

it('uses localized description when none provided', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: 10,
        description: null
    );

    $context = discountRewardContext($this->user, $this->promotion);
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

    $context = discountRewardContext($this->user, $this->promotion);
    $this->action->apply($context, $invalidConfig);

    expect($context->reward_intents)->toBeEmpty();
});
