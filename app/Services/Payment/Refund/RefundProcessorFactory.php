<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Contracts\Payment\RefundProcessorInterface;
use InvalidArgumentException;

final class RefundProcessorFactory
{
    public function make(string $paymentMethod): RefundProcessorInterface
    {
        return match ($paymentMethod) {
            'digipay' => app(DigipayRefundProcessor::class),
            'wallet'  => app(WalletRefundProcessor::class),
            'bank_transfer',
            'mellat_gateway' => app(ManualRefundProcessor::class),
            default          => throw new InvalidArgumentException(
                "No refund processor for payment method: {$paymentMethod}"
            ),
        };
    }
}
