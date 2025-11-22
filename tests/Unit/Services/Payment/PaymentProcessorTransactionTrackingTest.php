<?php

declare(strict_types=1);

use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Payment\PaymentMethodEnum;
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

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: null,
    );

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($order, $paymentData, $user, 500000);

    expect($result->payment)->toBeInstanceOf(Payment::class);

    // Verify transaction record was created
    $transaction = PaymentTransaction::where('payment_id', $result->payment->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->transaction_reference)->toBe('200000001');
    expect($transaction->status)->toBe(PaymentTransactionStatusEnum::COMPLETED);
    expect($transaction->attempt_number)->toBe(1);
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
        'method'        => PaymentMethodEnum::WALLET->value,
        'attempt_count' => 1,
    ]);

    // Create first transaction
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'transaction_reference' => '200000001',
        'attempt_number'        => 1,
        'status'                => PaymentTransactionStatusEnum::FAILED,
    ]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: null,
    );

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($order, $paymentData, $user, 500000);

    // Verify new transaction has incremented attempt number
    $latestTransaction = PaymentTransaction::where('payment_id', $result->payment->id)
        ->latest('created_at')
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

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: null,
    );

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($order, $paymentData, $user, 500000);

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

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: null,
    );

    $processor = app(WalletPaymentProcessor::class);
    $result    = $processor->process($order, $paymentData, $user, 500000);

    $payment = $result->payment;
    expect($payment->last_gateway_reference)->toBe('200000001');
    expect($payment->attempt_count)->toBe(1);
    expect($payment->last_attempted_at)->not->toBeNull();
});

it('generates unique transaction references for concurrent payments', function (): void {
    $user   = User::factory()->create();
    $wallet = $user->wallet;
    $wallet->update(['balance' => 5000000]);

    $order1 = Order::factory()->create(['customer_id' => $user->id, 'grand_total' => 100000, 'full_value_grand_total' => 100000]);
    $order2 = Order::factory()->create(['customer_id' => $user->id, 'grand_total' => 100000, 'full_value_grand_total' => 100000]);
    $order3 = Order::factory()->create(['customer_id' => $user->id, 'grand_total' => 100000, 'full_value_grand_total' => 100000]);

    $paymentData = new PaymentCreateData(
        method: PaymentMethodEnum::WALLET->value,
        data: null,
        admin_notes: null,
    );

    $processor = app(WalletPaymentProcessor::class);

    $result1 = $processor->process($order1, $paymentData, $user, 100000);
    $result2 = $processor->process($order2, $paymentData, $user, 100000);
    $result3 = $processor->process($order3, $paymentData, $user, 100000);

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
