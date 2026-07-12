<?php

declare(strict_types=1);

use App\Actions\Shop\Wallet\TopupWalletAction;
use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

describe('TopupWalletAction', function (): void {

    it('credits wallet for completed topup payment', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::COMPLETED,
            'method' => PaymentMethodEnum::MELLAT_GATEWAY,
            'amount' => 500000,
        ]);

        $this->mock(RecordWalletTransactionAction::class, function (MockInterface $mock) use ($payment): void {
            $mock->shouldReceive('execute')
                ->once()
                ->with(Mockery::on(function ($data) use ($payment): bool {
                    return $data->user_id   === $payment->customer_id
                        && $data->amount    === 500000
                        && $data->source_id === $payment->id;
                }));
        });

        $action = app(TopupWalletAction::class);
        $action->handle($payment);
    })->group('wallet');

    it('throws for non-WALLET_TOPUP purpose', function (): void {
        $payment = Payment::factory()->create([
            'purpose' => PaymentPurposeEnum::ORDER,
            'status'  => PaymentStatusEnum::COMPLETED,
        ]);

        $action = app(TopupWalletAction::class);

        expect(fn () => $action->handle($payment))
            ->toThrow(
                ValidationException::class,
                __('Payment :uuid is not a wallet topup payment.', ['uuid' => $payment->uuid])
            );
    })->group('wallet');

    it('throws for non-completed payment', function (): void {
        $payment = Payment::factory()->topup()->create([
            'status' => PaymentStatusEnum::PENDING,
        ]);

        $action = app(TopupWalletAction::class);

        expect(fn () => $action->handle($payment))
            ->toThrow(
                ValidationException::class,
                __('Payment :uuid is not completed.', ['uuid' => $payment->uuid])
            );
    })->group('wallet');

});
