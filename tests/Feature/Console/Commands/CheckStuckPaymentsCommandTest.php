<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\artisan;

it('detects stuck payments that have not completed after threshold', function (): void {
    Log::spy();

    $order   = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);

    // Create a stuck transaction (initiated 45 minutes ago, not completed)
    $stuckTransaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000001',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    artisan('payments:check-stuck --threshold=30')
        ->expectsOutput('Checking for stuck payments (threshold: 30 minutes)...')
        ->expectsOutput('Found 1 stuck payment(s):')
        ->assertExitCode(0);
});

it('does not detect payments within threshold', function (): void {
    Log::spy();

    $order   = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);

    // Create a recent transaction (initiated 10 minutes ago)
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000001',
        'initiated_at'          => now()->subMinutes(10),
        'completed_at'          => null,
    ]);

    artisan('payments:check-stuck --threshold=30')
        ->expectsOutput('No stuck payments found.')
        ->assertExitCode(0);

    Log::shouldNotHaveReceived('warning');
});

it('ignores completed transactions', function (): void {
    Log::spy();

    $order   = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);

    // Create a completed transaction
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::COMPLETED,
        'transaction_reference' => '200000001',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => now()->subMinutes(44),
    ]);

    artisan('payments:check-stuck --threshold=30')
        ->expectsOutput('No stuck payments found.')
        ->assertExitCode(0);

    Log::shouldNotHaveReceived('warning');
});

it('ignores failed transactions', function (): void {
    Log::spy();

    $order   = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::FAILED,
    ]);

    // Create a failed transaction
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::FAILED,
        'transaction_reference' => '200000001',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => now()->subMinutes(44),
    ]);

    artisan('payments:check-stuck --threshold=30')
        ->expectsOutput('No stuck payments found.')
        ->assertExitCode(0);

    Log::shouldNotHaveReceived('warning');
});

it('accepts custom threshold parameter', function (): void {
    Log::spy();

    $order   = Order::factory()->create();
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);

    // Create a transaction initiated 65 minutes ago
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000001',
        'initiated_at'          => now()->subMinutes(65),
        'completed_at'          => null,
    ]);

    // With threshold of 60 minutes, it should be detected
    artisan('payments:check-stuck --threshold=60')
        ->expectsOutput('Checking for stuck payments (threshold: 60 minutes)...')
        ->assertExitCode(0);

    Log::shouldHaveReceived('warning')->once();
});

it('handles multiple stuck payments', function (): void {
    Log::spy();

    $order1   = Order::factory()->create();
    $payment1 = Payment::factory()->create([
        'order_id' => $order1->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment1->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000001',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    $order2   = Order::factory()->create();
    $payment2 = Payment::factory()->create([
        'order_id' => $order2->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $payment2->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000002',
        'initiated_at'          => now()->subMinutes(50),
        'completed_at'          => null,
    ]);

    artisan('payments:check-stuck --threshold=30')
        ->expectsOutput('Found 2 stuck payment(s):')
        ->assertExitCode(0);

    Log::shouldHaveReceived('warning')->twice();
});
