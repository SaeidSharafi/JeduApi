<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Exceptions\Payment\InvalidPaymentPurposeException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use App\Services\Payment\BankTransferPaymentProcessor;
use Illuminate\Support\Facades\Event;

describe('BankTransferPaymentProcessor', function (): void {
    it('handles only bank transfer payments without redirect', function (): void {
        $processor = new BankTransferPaymentProcessor();

        expect($processor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeTrue()
            ->and($processor->canHandle(PaymentMethodEnum::WALLET))->toBeFalse()
            ->and($processor->requiresRedirect())->toBeFalse();
    });

    it('creates pending payment for customer initiated transfer', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method'   => PaymentMethodEnum::BANK_TRANSFER->value,
            'amount'   => 200_000,
            'status' => PaymentStatusEnum::PENDING
        ]);

        $processor = new BankTransferPaymentProcessor();
        $amount    = 200_000;
        $result    = $processor->process($payment);

        expect($result->payment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($result->payment->amount)->toBe($amount)
            ->and($result->requiresRedirect())->toBeFalse();

        Event::assertDispatched(PaymentCompletedEvent::class);
    });

    it('dispatches completion event when staff completes transfer', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $staff = Staff::factory()->create();
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);

        $processor = new BankTransferPaymentProcessor();
        $payment   = Payment::factory()->create([
            'status'   => PaymentStatusEnum::PENDING,
            'order_id' => $order->id,
            'method'   => PaymentMethodEnum::BANK_TRANSFER->value,
            'amount'   => 350_000,
        ]);

        $result = $processor->process($payment);

        expect($result->payment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($result->payment->method)->toBe(PaymentMethodEnum::BANK_TRANSFER);

        Event::assertDispatched(PaymentCompletedEvent::class, function (PaymentCompletedEvent $event) use ($result): bool {
            return $event->payment->is($result->payment);
        });
    });

    it('throws InvalidPaymentPurposeException when purpose is not ORDER', function (): void {
        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method'   => PaymentMethodEnum::BANK_TRANSFER->value,
            'purpose'  => PaymentPurposeEnum::WALLET_TOPUP,
            'amount'   => 100_000,
            'status'   => PaymentStatusEnum::PENDING,
        ]);

        $processor = new BankTransferPaymentProcessor();

        expect(fn () => $processor->process($payment))
            ->toThrow(InvalidPaymentPurposeException::class);
    });

    it('returns early without dispatching event when payment is already completed', function (): void {
        Event::fake([PaymentCompletedEvent::class]);

        $user  = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id'            => $user->id,
            'customer_email'         => $user->email,
            'customer_phone'         => $user->phone,
            'customer_first_name'    => $user->first_name,
            'customer_last_name'     => $user->last_name,
            'customer_snapshot_json' => $user->toArray(),
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method'   => PaymentMethodEnum::BANK_TRANSFER->value,
            'amount'   => 100_000,
            'status'   => PaymentStatusEnum::COMPLETED,
        ]);

        $processor = new BankTransferPaymentProcessor();
        $processor->process($payment);

        Event::assertNotDispatched(PaymentCompletedEvent::class);
    });

    it('throws bad method call when verify is invoked', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::BANK_TRANSFER->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $processor = new BankTransferPaymentProcessor();

        expect(fn (): Payment => $processor->verify($payment, []))
            ->toThrow(BadMethodCallException::class);
    });
});
