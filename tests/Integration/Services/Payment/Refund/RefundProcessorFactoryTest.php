<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Services\Payment\Refund\DigipayRefundProcessor;
use App\Services\Payment\Refund\ManualRefundProcessor;
use App\Services\Payment\Refund\RefundProcessorFactory;
use App\Services\Payment\Refund\WalletRefundProcessor;

it('resolves DigipayRefundProcessor for digipay method', function (): void {
    $factory = resolve(RefundProcessorFactory::class);
    $processor = $factory->make(PaymentMethodEnum::DIGIPAY->value);

    expect($processor)->toBeInstanceOf(DigipayRefundProcessor::class);
});

it('resolves WalletRefundProcessor for wallet method', function (): void {
    $factory = resolve(RefundProcessorFactory::class);
    $processor = $factory->make(PaymentMethodEnum::WALLET->value);

    expect($processor)->toBeInstanceOf(WalletRefundProcessor::class);
});

it('resolves ManualRefundProcessor for bank_transfer method', function (): void {
    $factory = resolve(RefundProcessorFactory::class);
    $processor = $factory->make(PaymentMethodEnum::BANK_TRANSFER->value);

    expect($processor)->toBeInstanceOf(ManualRefundProcessor::class);
});

it('resolves ManualRefundProcessor for mellat_gateway method', function (): void {
    $factory = resolve(RefundProcessorFactory::class);
    $processor = $factory->make(PaymentMethodEnum::MELLAT_GATEWAY->value);

    expect($processor)->toBeInstanceOf(ManualRefundProcessor::class);
});

it('throws exception for unknown payment method', function (): void {
    $factory = resolve(RefundProcessorFactory::class);

    expect(fn () => $factory->make('unknown_method'))
        ->toThrow(InvalidArgumentException::class, 'No refund processor for payment method');
});
