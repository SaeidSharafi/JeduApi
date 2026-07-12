<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Exceptions\Payment\DuplicatePaymentException;
use App\Exceptions\Payment\InvalidPaymentPurposeException;
use App\Services\Payment\WalletPaymentProcessor;
use Illuminate\Support\Facades\Event;

describe('WalletPaymentProcessor', function (): void {
    it('accepts wallet payments and does not require redirect', function (): void {
        $processor = new WalletPaymentProcessor(
            Mockery::mock(RecordWalletTransactionAction::class),
            Mockery::mock(App\Services\PaymentTransactionReferenceService::class)
        );

        expect($processor->canHandle(PaymentMethodEnum::WALLET))->toBeTrue()
            ->and($processor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeFalse()
            ->and($processor->requiresRedirect())->toBeFalse();
    });

    it('records wallet debit and completes payment when balance is sufficient', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $user = User::factory()->create();
        $user->wallet->update(['balance' => 1_000_000]);

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $amount  = 450_000;
        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => $amount,
            'method'      => PaymentMethodEnum::WALLET,
            'purpose'     => PaymentPurposeEnum::ORDER,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $processor = new WalletPaymentProcessor(
            app(RecordWalletTransactionAction::class),
            app(App\Services\PaymentTransactionReferenceService::class)
        );

        $result = $processor->process($payment);

        expect($result->payment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($result->payment->amount)->toBe($amount)
            ->and($result->payment->method)->toBe(PaymentMethodEnum::WALLET);

        expect($user->wallet->fresh()->balance)->toBe(1_000_000 - $amount)
            ->and(WalletTransaction::where('user_id', $user->id)->count())->toBe(1);

        Event::assertDispatched(PaymentCompletedEvent::class, function (PaymentCompletedEvent $event) use ($result): bool {
            return $event->payment->is($result->payment);
        });
    });

    it('fails when wallet balance is insufficient', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $user = User::factory()->create();
        $user->wallet->update(['balance' => 10_000]);

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $payment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 50_000,
            'method'      => PaymentMethodEnum::WALLET,
            'purpose'     => PaymentPurposeEnum::ORDER,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $processor = new WalletPaymentProcessor(
            app(RecordWalletTransactionAction::class),
            app(App\Services\PaymentTransactionReferenceService::class)
        );

        $processor->process($payment);
    })->throws(\App\Exceptions\Wallet\WalletInsufficientBalanceException::class);

    it('throws bad method call when verify is invoked', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::WALLET->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $processor = new WalletPaymentProcessor(
            Mockery::mock(RecordWalletTransactionAction::class),
            Mockery::mock(App\Services\PaymentTransactionReferenceService::class)
        );

        expect(fn (): Payment => $processor->verify($payment, []))
            ->toThrow(BadMethodCallException::class);
    });

    // ─── Guard: InvalidPaymentPurposeException (line 46) ────────────────

    it('throws InvalidPaymentPurposeException when purpose is not ORDER', function (): void {
        // Arrange
        $payment = Payment::factory()->topup()->create([
            'purpose' => PaymentPurposeEnum::WALLET_TOPUP,
            'method'  => PaymentMethodEnum::WALLET,
            'status'  => PaymentStatusEnum::PENDING,
        ]);

        $processor = new WalletPaymentProcessor(
            Mockery::mock(RecordWalletTransactionAction::class),
            Mockery::mock(App\Services\PaymentTransactionReferenceService::class)
        );

        // Act & Assert
        expect(fn (): PaymentProcessResultData => $processor->process($payment))
            ->toThrow(InvalidPaymentPurposeException::class)
            ->and(fn () => $processor->process($payment))
            ->toThrow(InvalidPaymentPurposeException::class, "This payment processor requires purpose 'order', got 'wallet_topup'.");
    });

    // ─── Guard: Non-PENDING early return (lines 50, 73) ─────────────────

    it('returns PaymentProcessResultData::completed immediately for non-PENDING payment', function (): void {
        // Arrange
        $payment = Payment::factory()->create([
            'purpose' => PaymentPurposeEnum::ORDER,
            'method'  => PaymentMethodEnum::WALLET,
            'status'  => PaymentStatusEnum::COMPLETED,
        ]);

        $processor = new WalletPaymentProcessor(
            Mockery::mock(RecordWalletTransactionAction::class),
            Mockery::mock(App\Services\PaymentTransactionReferenceService::class)
        );

        // Act
        $result = $processor->process($payment);

        // Assert
        expect($result)->toBeInstanceOf(PaymentProcessResultData::class)
            ->and($result->requiresRedirect())->toBeFalse()
            ->and($result->payment->status)->toBe(PaymentStatusEnum::COMPLETED);
    });

    // ─── Guard: DuplicatePaymentException (line 67) ─────────────────────

    it('throws DuplicatePaymentException when order already has completed wallet payment', function (): void {
        // Arrange
        $user = User::factory()->create();
        $user->wallet->update(['balance' => 1_000_000]);

        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        // Existing completed wallet payment
        Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 100_000,
            'method'      => PaymentMethodEnum::WALLET,
            'purpose'     => PaymentPurposeEnum::ORDER,
            'status'      => PaymentStatusEnum::COMPLETED,
        ]);

        // New pending wallet payment — should be blocked
        $pendingPayment = Payment::factory()->create([
            'order_id'    => $order->id,
            'customer_id' => $user->id,
            'amount'      => 200_000,
            'method'      => PaymentMethodEnum::WALLET,
            'purpose'     => PaymentPurposeEnum::ORDER,
            'status'      => PaymentStatusEnum::PENDING,
        ]);

        $processor = new WalletPaymentProcessor(
            app(RecordWalletTransactionAction::class),
            app(App\Services\PaymentTransactionReferenceService::class)
        );

        // Act & Assert
        expect(fn (): PaymentProcessResultData => $processor->process($pendingPayment))
            ->toThrow(DuplicatePaymentException::class);
    });
});
