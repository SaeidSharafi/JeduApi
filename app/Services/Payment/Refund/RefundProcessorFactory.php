<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Contracts\Payment\RefundProcessorInterface;
use App\Enums\Payment\PaymentMethodEnum;
use InvalidArgumentException;

final class RefundProcessorFactory
{
    public function make(string $paymentMethod): RefundProcessorInterface
    {
        $paymentMethodEnum = PaymentMethodEnum::tryFrom($paymentMethod);
        return match ($paymentMethodEnum) {
            PaymentMethodEnum::DIGIPAY => app(DigipayRefundProcessor::class),
            PaymentMethodEnum::WALLET  => app(WalletRefundProcessor::class),
            PaymentMethodEnum::BANK_TRANSFER,
            PaymentMethodEnum::MELLAT_GATEWAY => app(ManualRefundProcessor::class),
            default          => throw new InvalidArgumentException(
                __('messages.payment.refund_processor_not_found', ['pm' => $paymentMethod])
            ),
        };
    }
}
