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
use App\Services\Discounts\Cart\Actions\AddGiftCreditAction;
use App\Services\Discounts\Configs\AddGiftCreditConfigData;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();

    $this->wallet = $this->user->wallet;
    $this->wallet->update([
        'balance' => 10000,
    ]);
    $this->promotion = DiscountPromotion::factory()->create([
        'name' => 'Test Promotion',
    ]);

    $this->mockRecordAction = $this->mock(RecordWalletTransactionAction::class);
    $this->action           = new AddGiftCreditAction($this->mockRecordAction);
});

it('returns correct config class', function (): void {
    expect(AddGiftCreditAction::getConfigClass())->toBe(AddGiftCreditConfigData::class);
});

it('applies fixed amount gift credit without expiration', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: null,
        description: 'Order completion gift'
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
            return $transactionData->user_id                  === $this->user->id
                && $transactionData->type                     === TransactionTypeEnum::GIFT
                && $transactionData->amount                   === 5000
                && $transactionData->source_type              === TransactionSourceEnum::PROMOTION
                && $transactionData->description              === 'Order completion gift'
                && $transactionData->expires_at               === null
                && $transactionData->metadata['credit_type']  === 'gift'
                && $transactionData->metadata['expires_days'] === null;
        });

    $this->action->apply($context, $config);
});

it('applies fixed amount gift credit with expiration', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 7500,
        per_item: false,
        expires_days: 30,
        description: 'Limited time gift'
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
                && $transactionData->type        === TransactionTypeEnum::GIFT
                && $transactionData->amount      === 7500
                && $transactionData->source_type === TransactionSourceEnum::PROMOTION
                && $transactionData->description === 'Limited time gift'
                && $transactionData->expires_at !== null
                && $transactionData->metadata['credit_type']  === 'gift'
                && $transactionData->metadata['expires_days'] === 30;
        });

    $this->action->apply($context, $config);
});

it('applies per-item gift credit', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 1000,
        per_item: true,
        expires_days: 15,
        description: 'Per item gift bonus'
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
            return $transactionData->amount                   === 5000 // (2 + 3) * 1000
                && $transactionData->type                     === TransactionTypeEnum::GIFT
                && $transactionData->metadata['expires_days'] === 15;
        });

    $this->action->apply($context, $config);
});

it('excludes prepayment items from per-item calculation', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 1000,
        per_item: true,
        expires_days: null,
        description: 'Per item gift bonus'
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

it('does not apply gift credit for zero amount', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 1000,
        per_item: true,
        expires_days: 7,
        description: 'Per item gift bonus'
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

it('does not apply gift credit when customer has no wallet', function (): void {
    $userWithoutWallet = User::factory()->create();
    $userWithoutWallet->wallet->delete();
    $userWithoutWallet->refresh();

    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: null,
        description: 'Order completion gift'
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

it('uses localized description when none provided', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: 10,
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

it('handles record action exceptions gracefully', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 5000,
        per_item: false,
        expires_days: 5,
        description: 'Test gift credit'
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

it('does not apply gift credit for invalid configuration type', function (): void {
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

it('includes correct metadata in gift transaction', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 3000,
        per_item: false,
        expires_days: 45,
        description: 'Special gift credit'
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
            $metadata = $transactionData->metadata;

            return $metadata['promotion_name'] === 'Test Promotion'
                && $metadata['credit_type']    === 'gift'
                && $metadata['expires_days']   === 45
                && isset($metadata['configuration'])
                && $metadata['configuration']['amount']   === 3000
                && $metadata['configuration']['per_item'] === false;
        });

    $this->action->apply($context, $config);
});

it('handles zero expiration days correctly', function (): void {
    $config = new AddGiftCreditConfigData(
        amount: 2500,
        per_item: false,
        expires_days: 0,
        description: 'Same day gift credit'
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
            return $transactionData->expires_at !== null // Should set expiration even for 0 days
                && $transactionData->metadata['expires_days'] === 0;
        });

    $this->action->apply($context, $config);
});
