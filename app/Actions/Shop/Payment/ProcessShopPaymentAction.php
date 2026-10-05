<?php

declare(strict_types=1);

namespace App\Actions\Shop\Payment;

use App\Data\Admin\Payment\PaymentProcessResultData;
use App\Exceptions\ShopPaymentProcessingException;
use App\Models\Payment;
use App\Services\Payment\PaymentProcessorFactory;
use Throwable;

final readonly class ProcessShopPaymentAction
{
    public function __construct(private PaymentProcessorFactory $processorFactory) {}

    public function handle(Payment $payment): PaymentProcessResultData
    {
        try {
            return $this->processorFactory->make($payment->method)->process($payment);
        } catch (Throwable $exception) {
            throw new ShopPaymentProcessingException($payment->fresh(), $exception);
        }
    }
}
