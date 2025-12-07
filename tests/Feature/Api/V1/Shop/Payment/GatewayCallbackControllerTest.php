<?php

declare(strict_types=1);

use App\Actions\Shop\Payment\VerifyPaymentAction;
use App\Data\Shop\Payment\GatewayCallbackData;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Mockery as m;

use function Pest\Laravel\postJson;

it('returns success response with order data when order payment is verified', function (): void {
    $order   = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id'     => $order->id,
        'payment_type' => PaymentTypeEnum::ORDER,
        'status'       => PaymentStatusEnum::PENDING,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '000000012345',
    ]);

    $callbackPayload = [
        'SaleOrderId' => '000000012345',
        'ResCode'     => '0',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->with(m::on(function (GatewayCallbackData $data) use ($callbackPayload): bool {
            expect($data->transaction_refrence)->toBe('000000012345');
            expect($data->gateway_response)->toBe($callbackPayload);

            return true;
        }))
        ->andReturn(tap($payment->fresh())->update(['status' => PaymentStatusEnum::COMPLETED]));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback'), $callbackPayload);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'payment_type',
            'payment',
            'order' => [
                'id',
                'increment_id',
                'status',
                'grand_total',
            ],
            'wallet_transaction',
            'wallet_balance',
        ],
    ]);
    $response->assertJson([
        'data' => [
            'payment_type'       => 'order',
            'wallet_transaction' => null,
            'wallet_balance'     => null,
        ],
    ]);
});

it('returns success response with wallet data when wallet topup payment is verified', function (): void {
    $user = App\Models\User::factory()->create();

    // User factory automatically creates a wallet via UserObserver
    $wallet = $user->wallet;
    $wallet->update([
        'balance'      => 100000,
        'gift_balance' => 0,
    ]);

    $payment = Payment::factory()->create([
        'order_id'     => null,
        'payment_type' => PaymentTypeEnum::WALLET_TOPUP,
        'customer_id'  => $user->id,
        'status'       => PaymentStatusEnum::PENDING,
        'amount'       => 500000,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '000000067890',
    ]);

    // Create wallet transaction directly without using factory to avoid wallet creation
    $walletTransaction = WalletTransaction::create([
        'wallet_id'          => $wallet->id,
        'user_id'            => $user->id,
        'type'               => 'deposit',
        'amount'             => 500000,
        'balance_after'      => 600000,
        'gift_balance_after' => 0,
        'source_type'        => 'payment',
        'source_id'          => $payment->id,
    ]);

    $callbackPayload = [
        'SaleOrderId' => '000000067890',
        'ResCode'     => '0',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->andReturn(tap($payment->fresh())->update(['status' => PaymentStatusEnum::COMPLETED]));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback'), $callbackPayload);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data' => [
            'payment_type',
            'payment',
            'order',
            'wallet_transaction' => [
                'id',
                'amount',
                'balance_after',
            ],
            'wallet_balance' => [
                'balance',
                'gift_balance',
            ],
        ],
    ]);
    $response->assertJson([
        'data' => [
            'payment_type'   => 'wallet_topup',
            'order'          => null,
            'wallet_balance' => [
                'balance'      => 100000,
                'gift_balance' => 0,
            ],
        ],
    ]);
});

it('returns validation error when payment verification fails', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '000000012345',
    ]);

    $callbackPayload = [
        'SaleOrderId' => '000000012345',
        'ResCode'     => '12',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->andReturn(tap($payment)->setAttribute('status', PaymentStatusEnum::FAILED));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback'), $callbackPayload);

    $response->assertUnprocessable();
    $response->assertJsonStructure([
        'message',
        'errors' => [
            'payment',
        ],
    ]);
    $response->assertJson([
        'errors' => [
            'payment' => [[
                'error_code' => 'PAYMENT_VERIFICATION_FAILED',
            ]],
        ],
    ]);
});

it('returns error response when verification throws exception', function (): void {
    $payment = Payment::factory()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '000000012345',
    ]);

    $callbackPayload = [
        'SaleOrderId' => '000000012345',
        'ResCode'     => '999',
    ];

    $actionMock = m::mock(VerifyPaymentAction::class);
    $actionMock->expects('handle')
        ->once()
        ->andThrow(new RuntimeException('gateway boom'));

    app()->instance(VerifyPaymentAction::class, $actionMock);

    $response = postJson(route('api.v1.shop.payment.gateway.callback'), $callbackPayload);

    $response->assertStatus(500);
    $response->assertJsonStructure([
        'success',
        'message',
        'errors' => [
            'payment',
        ],
    ]);
    $response->assertJson([
        'success' => false,
        'errors'  => [
            'payment' => [[
                'error_code' => 'PAYMENT_PROCESSING_ERROR',
            ]],
        ],
    ]);
});
