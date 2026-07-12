<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

final class DuplicatePaymentException extends PaymentException
{
    public function __construct(
        public readonly int $paymentId,
        public readonly ?int $orderId = null,
    ) {
        parent::__construct(__('validation.custom.checkout.duplicate_payment'));
    }

    public function errorCode(): string
    {
        return 'DUPLICATE_PAYMENT';
    }

    protected function customMetadata(): array
    {
        return array_filter([
            'payment_id' => $this->paymentId,
            'order_id'   => $this->orderId,
        ]);
    }
}
