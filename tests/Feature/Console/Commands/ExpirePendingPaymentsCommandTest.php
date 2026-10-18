<?php

declare(strict_types=1);

use App\Console\Commands\ExpirePendingPaymentsCommand;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Payment\PaymentTransactionErrorCodeEnum;
use App\Enums\Payment\PaymentTransactionStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;

mutates(ExpirePendingPaymentsCommand::class);

it('expires a stale attempt on an order that is no longer payable', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
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

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('Checking for pending payment attempts older than 30 minutes...')
        ->expectsOutput('Found 1 pending payment attempt(s):')
        ->assertExitCode(0);

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED);

    $stuckTransaction->refresh();
    expect($stuckTransaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
        ->and($stuckTransaction->error_code)->toBe(PaymentTransactionErrorCodeEnum::STUCK_TIMEOUT->value)
        ->and($stuckTransaction->completed_at)->not->toBeNull();
});

it('leaves attempts on a pending order to the abandoned-order canceller', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PENDING]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);

    $transaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000001',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('No pending payment attempts to expire.')
        ->assertExitCode(0);

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::PENDING)
        ->and($transaction->fresh()->status)->toBe(PaymentTransactionStatusEnum::INITIATED);
});

it('uses ORDER_CLOSED when the payment belongs to a closed order', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::CANCELLED]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);
    $transaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000007',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('Found 1 pending payment attempt(s):')
        ->assertExitCode(0);

    $transaction->refresh();
    expect($transaction->status)->toBe(PaymentTransactionStatusEnum::FAILED)
        ->and($transaction->error_code)->toBe(PaymentTransactionErrorCodeEnum::ORDER_CLOSED->value)
        ->and($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
});

it('does not overwrite a payment completed after it was selected', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);
    $stuckTransaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000005',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    // Simulate a gateway callback completing the payment between the
    // eligibility query (which hydrates it as pending) and the row lock.
    $completed = false;
    Payment::retrieved(function (Payment $retrieved) use ($payment, &$completed): void {
        if ($retrieved->is($payment) && ! $completed) {
            $completed = true;
            DB::table('payments')
                ->where('id', $payment->id)
                ->update(['status' => PaymentStatusEnum::COMPLETED->value]);
        }
    });

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('Found 1 pending payment attempt(s):')
        ->assertExitCode(0);

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::COMPLETED)
        ->and($stuckTransaction->fresh()->status)->toBe(PaymentTransactionStatusEnum::INITIATED);
});

it('keeps a payment active while a newer attempt is still in flight', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'status'   => PaymentStatusEnum::PENDING,
    ]);

    $stale = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000003',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    $fresh = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000004',
        'initiated_at'          => now()->subMinutes(5),
        'completed_at'          => null,
    ]);

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('No pending payment attempts to expire.')
        ->assertExitCode(0);

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::PENDING)
        ->and($stale->fresh()->status)->toBe(PaymentTransactionStatusEnum::INITIATED)
        ->and($fresh->fresh()->status)->toBe(PaymentTransactionStatusEnum::INITIATED);
});

it('expires a pending wallet top-up payment with no order', function (): void {
    $payment = Payment::factory()->topup()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);

    $stuckTransaction = PaymentTransaction::factory()->create([
        'payment_id'            => $payment->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000006',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('Found 1 pending payment attempt(s):')
        ->assertExitCode(0);

    expect($payment->fresh()->order_id)->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED)
        ->and($stuckTransaction->fresh()->status)->toBe(PaymentTransactionStatusEnum::FAILED)
        ->and($stuckTransaction->fresh()->error_code)->toBe(PaymentTransactionErrorCodeEnum::STUCK_TIMEOUT->value);
});

it('expires a wallet top-up that never reached the gateway', function (): void {
    $payment = Payment::factory()->topup()->create([
        'status'            => PaymentStatusEnum::PENDING,
        'created_at'        => now()->subMinutes(45),
        'last_attempted_at' => now()->subMinutes(45),
    ]);

    expect($payment->transactions()->exists())->toBeFalse();

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('Found 1 pending payment attempt(s):')
        ->assertExitCode(0);

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
});

it('does not detect payments within threshold', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
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

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('No pending payment attempts to expire.')
        ->assertExitCode(0);
});

it('ignores completed transactions', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
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

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('No pending payment attempts to expire.')
        ->assertExitCode(0);
});

it('ignores failed transactions', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
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

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('No pending payment attempts to expire.')
        ->assertExitCode(0);
});

it('accepts custom threshold parameter', function (): void {
    $order   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
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
    artisan('payments:expire-pending --threshold=60')
        ->expectsOutput('Checking for pending payment attempts older than 60 minutes...')
        ->assertExitCode(0);

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
});

it('handles multiple pending payment attempts', function (): void {
    $order1   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
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

    $order2   = Order::factory()->create(['status' => OrderStatusEnum::PROCESSING]);
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

    artisan('payments:expire-pending --threshold=30')
        ->expectsOutput('Found 2 pending payment attempt(s):')
        ->assertExitCode(0);

    expect($payment1->fresh()->status)->toBe(PaymentStatusEnum::FAILED)
        ->and($payment2->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
});

it('falls back to the configured abandonment window for the threshold', function (): void {
    config(['order.abandonment_minutes' => 60]);

    // 45 minutes old: past the 30-minute default, inside the configured window.
    $recent = Payment::factory()->topup()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $recent->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000021',
        'initiated_at'          => now()->subMinutes(45),
        'completed_at'          => null,
    ]);

    // 65 minutes old: past the configured window.
    $stale = Payment::factory()->topup()->create([
        'status' => PaymentStatusEnum::PENDING,
    ]);
    PaymentTransaction::factory()->create([
        'payment_id'            => $stale->id,
        'status'                => PaymentTransactionStatusEnum::INITIATED,
        'transaction_reference' => '200000022',
        'initiated_at'          => now()->subMinutes(65),
        'completed_at'          => null,
    ]);

    artisan('payments:expire-pending')
        ->expectsOutput('Checking for pending payment attempts older than 60 minutes...')
        ->expectsOutput('Found 1 pending payment attempt(s):')
        ->assertExitCode(0);

    expect($recent->fresh()->status)->toBe(PaymentStatusEnum::PENDING)
        ->and($stale->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
});
