<?php

declare(strict_types=1);

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;

use function Pest\Laravel\postJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->customer($this->user);
    // Use automatically created wallet
    $this->wallet = $this->user->wallet;
    $this->wallet->update(['balance' => 500000]);
});

it('returns structured error when wallet balance is insufficient', function (): void {
    $order = Order::factory()->create([
        'customer_id'            => $this->user->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $response = postJson(
        route('api.v1.shop.orders.retry-payment', $order->increment_id),
        ['payment_method' => PaymentMethodEnum::WALLET->value]
    );

    $response->assertStatus(422);
    $response->assertJsonStructure([
        'message',
        'errors' => [
            'error_code',
            'available_balance',
            'required_balance',
            'shortfall',
            'redirect_suggestion',
        ],
    ]);

    $response->assertJson([
        'errors' => [
            'error_code'          => 'INSUFFICIENT_WALLET_BALANCE',
            'available_balance'   => 500000,
            'required_balance'    => 1000000,
            'shortfall'           => 500000,
            'redirect_suggestion' => 'wallet-topup',
        ],
    ]);
});

it('processes payment successfully when wallet has sufficient balance', function (): void {
    // Update wallet with sufficient balance
    $this->wallet->update(['balance' => 1500000]);

    $order = Order::factory()->create([
        'customer_id'            => $this->user->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $response = postJson(
        route('api.v1.shop.orders.retry-payment', $order->increment_id),
        ['payment_method' => PaymentMethodEnum::WALLET->value]
    );

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'message',
            'payment',
            'requires_redirect',
        ],
    ]);

    // Verify wallet balance was deducted
    $this->wallet->refresh();
    expect($this->wallet->balance)->toBe(500000);
});

it('provides exact shortfall amount in error response', function (): void {
    // Wallet has 750000, order needs 1000000
    $this->wallet->update(['balance' => 750000]);

    $order = Order::factory()->create([
        'customer_id'            => $this->user->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $response = postJson(
        route('api.v1.shop.orders.retry-payment', $order->increment_id),
        ['payment_method' => PaymentMethodEnum::WALLET->value]
    );

    $response->assertStatus(422);
    $response->assertJson([
        'errors' => [
            'error_code'        => 'INSUFFICIENT_WALLET_BALANCE',
            'available_balance' => 750000,
            'required_balance'  => 1000000,
            'shortfall'         => 250000,
        ],
    ]);
});

it('returns error when wallet balance is zero', function (): void {
    $this->wallet->update(['balance' => 0]);

    $order = Order::factory()->create([
        'customer_id'            => $this->user->id,
        'status'                 => OrderStatusEnum::PENDING,
        'grand_total'            => 1000000,
        'full_value_grand_total' => 1000000,
    ]);

    $response = postJson(
        route('api.v1.shop.orders.retry-payment', $order->increment_id),
        ['payment_method' => PaymentMethodEnum::WALLET->value]
    );

    $response->assertStatus(422);
    $response->assertJson([
        'errors' => [
            'error_code'        => 'INSUFFICIENT_WALLET_BALANCE',
            'available_balance' => 0,
            'required_balance'  => 1000000,
            'shortfall'         => 1000000,
        ],
    ]);
});
