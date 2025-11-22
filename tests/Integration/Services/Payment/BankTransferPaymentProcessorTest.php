<?php

declare(strict_types=1);

use App\Data\Admin\Payment\BankTransferPaymentData;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\User;
use App\Services\Payment\BankTransferPaymentProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

describe('BankTransferPaymentProcessor', function (): void {
    it('handles only bank transfer payments without redirect', function (): void {
        $processor = new BankTransferPaymentProcessor();

        expect($processor->canHandle(PaymentMethodEnum::BANK_TRANSFER))->toBeTrue()
            ->and($processor->canHandle(PaymentMethodEnum::WALLET))->toBeFalse()
            ->and($processor->requiresRedirect())->toBeFalse();
    });

    it('validates bank transfer details for staff payments', function (): void {
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

        $processor   = new BankTransferPaymentProcessor();
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::BANK_TRANSFER->value,
            data: null,
            admin_notes: null,
        );

        $processor->process($order, $paymentData, $staff, 100_000);
    })->throws(ValidationException::class);

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

        $processor   = new BankTransferPaymentProcessor();
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::BANK_TRANSFER->value,
            data: null,
            admin_notes: 'Retry payment',
        );

        $amount = 200_000;
        $result = $processor->process($order, $paymentData, $user, $amount);

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

        $processor   = new BankTransferPaymentProcessor();
        $paymentData = new PaymentCreateData(
            method: PaymentMethodEnum::BANK_TRANSFER->value,
            data: new BankTransferPaymentData(
                transaction_id: 'TRX-123456',
                transaction_date: '1403-01-01',
                sender_name: 'Test Sender',
                notes: 'Paid in branch',
            ),
            admin_notes: 'Marked as paid',
        );

        $result = $processor->process($order, $paymentData, $staff, 350_000);

        expect($result->payment->status)->toBe(PaymentStatusEnum::COMPLETED)
            ->and($result->payment->method)->toBe(PaymentMethodEnum::BANK_TRANSFER);

        Event::assertDispatched(PaymentCompletedEvent::class, function (PaymentCompletedEvent $event) use ($result): bool {
            return $event->payment->is($result->payment);
        });
    });

    it('throws bad method call when verify is invoked', function (): void {
        $payment = Payment::factory()->create([
            'method' => PaymentMethodEnum::BANK_TRANSFER->value,
            'status' => PaymentStatusEnum::PENDING->value,
            'data'   => [],
        ]);

        $processor = new BankTransferPaymentProcessor();

        expect(fn () => $processor->verify($payment, []))
            ->toThrow(BadMethodCallException::class);
    });
});
