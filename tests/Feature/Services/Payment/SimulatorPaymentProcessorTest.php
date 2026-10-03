<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payment\SimulatorPaymentProcessor;
use App\Services\PaymentTransactionReferenceService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'payments.simulator.enabled'  => true,
        'payments.simulator.base_url' => 'http://payment-simulator.test',
        'payments.simulator.secret'   => 'e2e-secret',
        'payments.simulator.timeout'  => 10,
    ]);
});

afterEach(function (): void {
    config(['payments.simulator.enabled' => false]);
});

function simulatorPayment(): Payment
{
    /** @var User $user */
    $user = User::factory()->create();
    /** @var Order $order */
    $order = Order::factory()->create([
        'customer_id'            => $user->id,
        'customer_email'         => $user->email,
        'customer_phone'         => $user->phone,
        'customer_first_name'    => $user->first_name,
        'customer_last_name'     => $user->last_name,
        'customer_snapshot_json' => $user->toArray(),
        'grand_total'            => 500_000,
        'full_value_grand_total' => 500_000,
    ]);

    /** @var Payment $payment */
    $payment = Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $user->id,
        'amount'      => 500_000,
        'method'      => PaymentMethodEnum::SIMULATOR,
        'status'      => PaymentStatusEnum::PENDING,
        'data'        => ['delay_seconds' => 3],
    ]);

    return $payment;
}

/** @param array<string, mixed> $payload */
function simulatorSignature(array $payload): string
{
    return hash_hmac(
        'sha256',
        (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'e2e-secret',
    );
}

function simulatorProcessor(): SimulatorPaymentProcessor
{
    return new SimulatorPaymentProcessor(app(PaymentTransactionReferenceService::class), true);
}

it('initiates an exact signed simulator attempt', function (): void {
    $payment = simulatorPayment();

    Http::fake([
        'payment-simulator.test/api/v1/attempts' => Http::response([
            'redirect_url' => 'http://payment-simulator.test/pay/attempt-1',
            'attempt_id'   => 'attempt-1',
        ]),
    ]);

    $result  = simulatorProcessor()->process($payment);
    $request = Http::recorded()[0][0];

    expect($result->redirect_url)->toBe('http://payment-simulator.test/pay/attempt-1')
        ->and($request->data())->toMatchArray([
            'order_reference'   => $payment->order->increment_id,
            'payment_reference' => $payment->transactions()->first()->transaction_reference,
            'amount'            => 500_000,
            'callback_url'      => route('api.v1.shop.payment.gateway.callback', ['payment' => $payment->uuid]),
            'delay_seconds'     => 3,
        ])
        ->and($request->header('X-Simulator-Signature')[0])->toBe(simulatorSignature($request->data()));
});

it('marks a failed attempt terminally and allows a successful retry', function (): void {
    Event::fake([PaymentCompletedEvent::class]);
    Http::fake([
        '*payment-simulator.test/api/v1/attempts*' => Http::sequence()
            ->push(['redirect_url' => 'http://payment-simulator.test/pay/attempt-1'])
            ->push(['redirect_url' => 'http://payment-simulator.test/pay/attempt-2']),
    ]);

    $payment   = simulatorPayment();
    $processor = simulatorProcessor();
    $processor->process($payment);
    /** @var PaymentTransaction $transaction */
    $transaction = $payment->fresh()->transactions()->firstOrFail();
    $failure     = [
        'order_reference'   => $payment->order->increment_id,
        'payment_reference' => $transaction->transaction_reference,
        'amount'            => $payment->amount,
        'outcome'           => 'failure',
    ];
    $failure['signature'] = simulatorSignature($failure);

    $processor->verify($payment->fresh(), $failure);
    $processor->verify($payment->fresh(), $failure);

    /** @var Payment $retry */
    $retry = Payment::factory()->create([
        'order_id'    => $payment->order_id,
        'customer_id' => $payment->customer_id,
        'amount'      => $payment->amount,
        'method'      => PaymentMethodEnum::SIMULATOR,
        'status'      => PaymentStatusEnum::PENDING,
    ]);
    $processor->process($retry);
    /** @var PaymentTransaction $retryTransaction */
    $retryTransaction = $retry->fresh()->transactions()->firstOrFail();
    $success          = [
        'order_reference'   => $payment->order->increment_id,
        'payment_reference' => $retryTransaction->transaction_reference,
        'amount'            => $retry->amount,
        'outcome'           => 'success',
    ];
    $success['signature'] = simulatorSignature($success);

    $processor->verify($retry->fresh(), $success);
    $processor->verify($retry->fresh(), $success);

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED)
        ->and($retry->fresh()->status)->toBe(PaymentStatusEnum::COMPLETED)
        ->and($payment->order_id)->toBe($retry->order_id)
        ->and($payment->order->payments()->count())->toBe(2)
        ->and($payment->transactions()->first()->status)->toBe(PaymentTransactionStatusEnum::FAILED)
        ->and($retry->transactions()->first()->status)->toBe(PaymentTransactionStatusEnum::COMPLETED);

    Event::assertDispatchedTimes(PaymentCompletedEvent::class, 1);
});

it('rejects malformed callback signatures and references before mutation', function (): void {
    Http::fake([
        'payment-simulator.test/api/v1/attempts' => Http::response([
            'redirect_url' => 'http://payment-simulator.test/pay/attempt-1',
        ]),
    ]);

    $payment   = simulatorPayment();
    $processor = simulatorProcessor();
    $processor->process($payment);
    /** @var PaymentTransaction $transaction */
    $transaction = $payment->fresh()->transactions()->firstOrFail();

    $callback = [
        'order_reference'   => 'wrong-order',
        'payment_reference' => $transaction->transaction_reference,
        'amount'            => $payment->amount,
        'outcome'           => 'success',
        'signature'         => 'invalid',
    ];

    expect(fn (): Payment => $processor->verify($payment->fresh(), $callback))
        ->toThrow(InvalidArgumentException::class);
    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::PENDING)
        ->and($transaction->fresh()->status)->toBe(PaymentTransactionStatusEnum::INITIATED);
});
