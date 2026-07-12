<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payment\WalletPaymentProcessor;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('payments.transaction_reference.start_from', 200000001);
    $this->referenceService = app(PaymentTransactionReferenceService::class);
});

it('creates a payment transaction record when processing wallet payment', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet; // auto-created
    $wallet->update(['balance' => 1000000]);

    $order = Order::factory()->create([
        'customer_id'            => $user->id,
        'grand_total'            => 500000,
        'full_value_grand_total' => 500000,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'amount'      => 500000,
        'method'      => PaymentMethodEnum::WALLET,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($payment);

    expect($result->payment)->toBeInstanceOf(Payment::class);

    // Verify transaction record was created
    $transaction = PaymentTransaction::where('payment_id', $result->payment->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->transaction_reference)->toBe('200000001');
    expect($transaction->status)->toBe(PaymentTransactionStatusEnum::COMPLETED);
    expect($transaction->attempt_number)->toBe(2);
    expect($transaction->initiated_at)->not->toBeNull();
    expect($transaction->completed_at)->not->toBeNull();
});

it('increments attempt number for subsequent transaction attempts', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $wallet->update(['balance' => 2000000]);

    $order = Order::factory()->create([
        'customer_id'            => $user->id,
        'grand_total'            => 500000,
        'full_value_grand_total' => 500000,
    ]);

    $payment = Payment::factory()->create([
        'order_id'      => $order->id,
        'customer_id'   => $user->id,
        'amount'        => 500000,
        'method'        => PaymentMethodEnum::WALLET,
        'purpose'       => PaymentPurposeEnum::ORDER,
        'status'        => PaymentStatusEnum::PENDING,
        'attempt_count' => 1,
    ]);

    // Create first transaction
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '200000001',
        'attempt_number'        => 1,
        'status'                => PaymentTransactionStatusEnum::FAILED,
    ]);

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($payment);

    // Verify new transaction has incremented attempt number
    $latestTransaction = PaymentTransaction::where('payment_id', $result->payment->id)
        ->latest('id')
        ->first();

    expect($latestTransaction->attempt_number)->toBe(2);
    expect($latestTransaction->transaction_reference)->toBe('200000002');
});

it('stores gateway metadata in transaction record', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $wallet->update(['balance' => 1000000]);

    $order = Order::factory()->create([
        'customer_id'            => $user->id,
        'grand_total'            => 500000,
        'full_value_grand_total' => 500000,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'amount'      => 500000,
        'method'      => PaymentMethodEnum::WALLET,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($payment);

    $transaction = PaymentTransaction::where('payment_id', $result->payment->id)->first();

    expect($transaction->gateway_request)->toBeArray();
    expect($transaction->gateway_request)->toHaveKeys(['wallet_id', 'amount', 'order_id']);
    expect($transaction->gateway_response)->toBeArray();
    expect($transaction->gateway_response)->toHaveKey('success');
});

it('updates payment with last_gateway_reference after transaction', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $wallet->update(['balance' => 1000000]);

    $order = Order::factory()->create([
        'customer_id'            => $user->id,
        'grand_total'            => 500000,
        'full_value_grand_total' => 500000,
    ]);

    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'amount'      => 500000,
        'method'      => PaymentMethodEnum::WALLET,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($payment);

    expect($result->payment->last_gateway_reference)->toBe('200000001');
    expect($result->payment->attempt_count)->toBe(2);
    expect($result->payment->last_attempted_at)->not->toBeNull();
});

it('generates unique transaction references for concurrent payments', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $wallet->update(['balance' => 5000000]);

    $order1 = Order::factory()->create(['customer_id' => $user->id, 'grand_total' => 100000, 'full_value_grand_total' => 100000]);
    $order2 = Order::factory()->create(['customer_id' => $user->id, 'grand_total' => 100000, 'full_value_grand_total' => 100000]);
    $order3 = Order::factory()->create(['customer_id' => $user->id, 'grand_total' => 100000, 'full_value_grand_total' => 100000]);

    $payment1 = Payment::factory()->create([
        'order_id'    => $order1->id,
        'customer_id' => $user->id,
        'amount'      => 100000,
        'method'      => PaymentMethodEnum::WALLET,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'status'      => PaymentStatusEnum::PENDING,
    ]);
    $payment2 = Payment::factory()->create([
        'order_id'    => $order2->id,
        'customer_id' => $user->id,
        'amount'      => 100000,
        'method'      => PaymentMethodEnum::WALLET,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'status'      => PaymentStatusEnum::PENDING,
    ]);
    $payment3 = Payment::factory()->create([
        'order_id'    => $order3->id,
        'customer_id' => $user->id,
        'amount'      => 100000,
        'method'      => PaymentMethodEnum::WALLET,
        'purpose'     => PaymentPurposeEnum::ORDER,
        'status'      => PaymentStatusEnum::PENDING,
    ]);

    $processor = app(WalletPaymentProcessor::class);

    $result1 = $processor->process($payment1);
    $result2 = $processor->process($payment2);
    $result3 = $processor->process($payment3);

    $transaction1 = PaymentTransaction::where('payment_id', $result1->payment->id)->first();
    $transaction2 = PaymentTransaction::where('payment_id', $result2->payment->id)->first();
    $transaction3 = PaymentTransaction::where('payment_id', $result3->payment->id)->first();

    expect($transaction1->transaction_reference)->toBe('200000001');
    expect($transaction2->transaction_reference)->toBe('200000002');
    expect($transaction3->transaction_reference)->toBe('200000003');

    // Ensure all are unique
    $references = [
        $transaction1->transaction_reference,
        $transaction2->transaction_reference,
        $transaction3->transaction_reference,
    ];
    expect(count($references))->toBe(count(array_unique($references)));
});
