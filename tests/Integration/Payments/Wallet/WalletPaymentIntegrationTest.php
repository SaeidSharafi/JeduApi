<?php

declare(strict_types=1);

use App\Actions\Admin\Payment\CreatePaymentAction;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

beforeEach(function (): void {
    $this->customer              = User::factory()->create();
    $this->productDeliveryOption = ProductDeliveryOption::factory()->create(['price' => 50000]);
    $this->authorized_user([
        PermissionEnum::WALLET_CREATE, PermissionEnum::WALLET_WITHDRAWAL, PermissionEnum::PAYMENT_CREATE,
    ]);
});

it('can create a wallet payment successfully', function (): void {
    // Create a wallet with sufficient balance
    $this->customer->wallet->update([
        'user_id' => $this->customer->id,
        'balance' => 100000,
    ]);

    // Create an order
    $items = [
        [
            'product_delivery_option_id' => $this->productDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'price'                      => 50000,
            'total'                      => 50000,
            'name'                       => 'Test Course',
        ],
    ];
    $order = Order::factory()
        ->withCalculatedTotals($items)
        ->create(['customer_id' => $this->customer->id])
        ->fresh();

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: 'Wallet payment test'
    );

    Event::fake();

    $payment = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->user)->payment;

    expect($payment->amount)->toBe(50000)
        ->and($payment->method)->toBe(PaymentMethodEnum::WALLET)
        ->and($payment->status)->toBe(PaymentStatusEnum::COMPLETED);

    $this->assertDatabaseHas('payments', [
        'id'       => $payment->id,
        'order_id' => $order->id,
        'amount'   => 50000,
        'method'   => PaymentMethodEnum::WALLET->value,
    ]);

    // Check wallet balance was deducted
    $this->customer->wallet->refresh();
    expect($this->customer->wallet->balance)->toBe(50000); // 100000 - 50000

    // Check wallet transaction was recorded
    $this->assertDatabaseHas('wallet_transactions', [
        'wallet_id'   => $this->customer->wallet->id,
        'amount'      => -50000,
        'type'        => TransactionTypeEnum::PAYMENT->value,
        'source_type' => TransactionSourceEnum::ORDER->value,
        'source_id'   => $order->id,
    ]);

    Event::assertDispatched(PaymentCompletedEvent::class);
});

it('fails when wallet has insufficient balance', function (): void {
    // Create a wallet with insufficient balance
    $this->customer->wallet->update([
        'user_id' => $this->customer->id,
        'balance' => 30000, // Less than order amount
    ]);

    // Create an order
    $items = [
        [
            'product_delivery_option_id' => $this->productDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'price'                      => 50000,
            'total'                      => 50000,
            'name'                       => 'Test Course',
        ],
    ];
    $order = Order::factory()
        ->withCalculatedTotals($items)
        ->create(['customer_id' => $this->customer->id]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: 'Wallet payment test'
    );

    expect(fn () => (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->user))
        ->toThrow(App\Exceptions\Payment\InsufficientWalletBalanceException::class);

    // Verify wallet balance unchanged
    $this->customer->wallet->refresh();
    expect($this->customer->wallet->balance)->toBe(30000);
});

it('can process pre-payment wallet payment', function (): void {
    // Create a wallet with sufficient balance
    $this->customer->wallet->update([
        'user_id'      => $this->customer->id,
        'balance'      => 100000,
        'gift_balance' => 0,
    ]);

    // Create an order with pre-payment items
    $items = [
        [
            'product_delivery_option_id' => $this->productDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
            'price'                      => 50000,
            'total'                      => 20000, // Pre-payment amount
            'name'                       => 'Test Course',
        ],
    ];
    $order = Order::factory()
        ->withCalculatedTotals($items)
        ->create(['customer_id' => $this->customer->id]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: 'Partial wallet payment'
    );

    $payment = (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->user)->payment;

    expect($payment->amount)->toBe(20000);

    // Check wallet balance was deducted
    $this->customer->wallet->refresh();

    expect($this->customer->wallet->balance)->toBe(80000); // 100000 - 20000

    // Order should still have balance due
    $order->refresh();
    expect($order->balance_due)->toBe(30000); // 50000 - 20000
});

it('processes wallet payment with localized messages', function (): void {
    // Create a wallet with insufficient balance to test error message
    $this->customer->wallet->update([
        'user_id' => $this->customer->id,
        'balance' => 30000,
    ]);

    $items = [
        [
            'product_delivery_option_id' => $this->productDeliveryOption->id,
            'qty_ordered'                => 1,
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
            'price'                      => 50000,
            'total'                      => 50000,
            'name'                       => 'Test Course',
        ],
    ];
    $order = Order::factory()
        ->withCalculatedTotals($items)
        ->create(['customer_id' => $this->customer->id]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: 'Test'
    );

    try {
        (app(CreatePaymentAction::class))->handle($order, $paymentData, $this->user);
    } catch (App\Exceptions\Payment\InsufficientWalletBalanceException $e) {
        expect($e->getAvailableBalance())
            ->toBe(30000)
            ->and($e->getRequiredBalance())->toBe(50000)
            ->and($e->getShortfall())->toBe(20000);
    }
});
