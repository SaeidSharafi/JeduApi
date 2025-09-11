<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\AddWalletCreditAction;
use App\Services\Discounts\Configs\AddWalletCreditConfigData;
use Tests\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->wallet = $this->user->wallet;
    $this->wallet->update([
        'balance' => 10000,
    ]);
    $this->promotion = DiscountPromotion::factory()->create([
        'name' => 'Test Promotion',
    ]);

    $this->mockRecordAction = $this->mock(RecordWalletTransactionAction::class);
    $this->action           = new AddWalletCreditAction($this->mockRecordAction);
});

it('returns correct config class', function () {
    expect(AddWalletCreditAction::getConfigClass())->toBe(AddWalletCreditConfigData::class);
});

it('applies fixed amount wallet credit', function () {
    $config = new AddWalletCreditConfigData(
        amount: 5000,
        per_item: false,
        description: 'Order completion bonus'
    );

    $context = new OrderContextData(
        customer: $this->user,
        items: collect([]),
        subtotal_full_payment_items: 10000,
        subtotal_all_items: 10000,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return $transactionData->user_id     === $this->user->id
                && $transactionData->type        === TransactionTypeEnum::BONUS
                && $transactionData->amount      === 5000
                && $transactionData->source_type === TransactionSourceEnum::PROMOTION
                && $transactionData->description === 'Order completion bonus';
        });

    $this->action->apply($context, $config);
});

it('applies per-item wallet credit', function () {
    $config = new AddWalletCreditConfigData(
        amount: 1000,
        per_item: true,
        description: 'Per item bonus'
    );

    $deliveryOption = ProductDeliveryOption::factory()->create();

    $items = collect([
        new CalculatedOrderItemData(
            product_delivery_option: $deliveryOption,
            qty: 2,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 5000,
            total: 10000
        ),
        new CalculatedOrderItemData(
            product_delivery_option: $deliveryOption,
            qty: 3,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 3000,
            total: 9000
        ),
    ]);

    $context = new OrderContextData(
        customer: $this->user,
        items: $items,
        subtotal_full_payment_items: 19000,
        subtotal_all_items: 19000,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return $transactionData->amount === 5000; // (2 + 3) * 1000
        });

    $this->action->apply($context, $config);
});

it('excludes prepayment items from per-item calculation', function () {
    $config = new AddWalletCreditConfigData(
        amount: 1000,
        per_item: true,
        description: 'Per item bonus'
    );

    $deliveryOption = ProductDeliveryOption::factory()->create();

    $items = collect([
        new CalculatedOrderItemData(
            product_delivery_option: $deliveryOption,
            qty: 2,
            payment_type: OrderItemPaymentTypeEnum::FULL_PAYMENT,
            price: 5000,
            total: 10000
        ),
        new CalculatedOrderItemData(
            product_delivery_option: $deliveryOption,
            qty: 3,
            payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT, // This should be excluded
            price: 3000,
            total: 9000
        ),
    ]);

    $context = new OrderContextData(
        customer: $this->user,
        items: $items,
        subtotal_full_payment_items: 10000,
        subtotal_all_items: 19000,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return $transactionData->amount === 2000; // Only 2 items * 1000
        });

    $this->action->apply($context, $config);
});

it('does not apply credit for zero amount', function () {
    $config = new AddWalletCreditConfigData(
        amount: 1000,
        per_item: true,
        description: 'Per item bonus'
    );

    $context = new OrderContextData(
        customer: $this->user,
        items: collect([]), // No items
        subtotal_full_payment_items: 0,
        subtotal_all_items: 0,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    $this->action->apply($context, $config);
});

it('does not apply credit when customer has no wallet', function () {
    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();
    $userWithoutWallet->refresh();
    $config = new AddWalletCreditConfigData(
        amount: 5000,
        per_item: false,
        description: 'Order completion bonus'
    );

    $context = new OrderContextData(
        customer: $userWithoutWallet,
        items: collect([]),
        subtotal_full_payment_items: 10000,
        subtotal_all_items: 10000,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    $this->action->apply($context, $config);
});

it('uses localized description when none provided', function () {
    $config = new AddWalletCreditConfigData(
        amount: 5000,
        per_item: false,
        description: null
    );

    $context = new OrderContextData(
        customer: $this->user,
        items: collect([]),
        subtotal_full_payment_items: 10000,
        subtotal_all_items: 10000,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->withArgs(function ($transactionData) {
            return str_contains($transactionData->description, 'Test Promotion');
        });

    $this->action->apply($context, $config);
});

it('handles record action exceptions gracefully', function () {
    $config = new AddWalletCreditConfigData(
        amount: 5000,
        per_item: false,
        description: 'Test bonus'
    );

    $context = new OrderContextData(
        customer: $this->user,
        items: collect([]),
        subtotal_full_payment_items: 10000,
        subtotal_all_items: 10000,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new Exception('Database error'));
    // Should not throw exception
    expect(fn () => $this->action->apply($context, $config))->not->toThrow(Exception::class);
});

it('does not apply credit for invalid configuration type', function () {
    $invalidConfig = new class extends Spatie\LaravelData\Data
    {
        public function toArray(): array
        {
            return [];
        }
    };

    $context = new OrderContextData(
        customer: $this->user,
        items: collect([]),
        subtotal_full_payment_items: 10000,
        subtotal_all_items: 10000,
        evaluating_promotion: $this->promotion
    );

    $this->mockRecordAction->shouldNotReceive('execute');

    $this->action->apply($context, $invalidConfig);
});
