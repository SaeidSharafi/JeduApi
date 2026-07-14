<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

final class InvalidPaymentPurposeException extends PaymentException
{
    public function __construct(
        public readonly string $expectedPurpose,
        public readonly string $actualPurpose,
    ) {
        parent::__construct(__('messages.exceptions.invalid_payment_purpose', ['expected' => $this->expectedPurpose, 'actual' => $this->actualPurpose]));
    }

    public function errorCode(): string
    {
        return 'INVALID_PAYMENT_PURPOSE';
    }

    protected function customUserMessage(): string
    {
        return __('validation.custom.checkout.payment_method_not_supported');
    }

    protected function customMetadata(): array
    {
        return [
            'expected_purpose' => $this->expectedPurpose,
            'actual_purpose'   => $this->actualPurpose,
        ];
    }
}
