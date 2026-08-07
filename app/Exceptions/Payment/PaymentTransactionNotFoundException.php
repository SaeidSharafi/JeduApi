<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

final class PaymentTransactionNotFoundException extends PaymentException
{
    public function __construct(
        public readonly string $reference,
    ) {
        parent::__construct("Transaction not found for reference: {$reference}");
    }

    public function errorCode(): string
    {
        return 'TRANSACTION_NOT_FOUND';
    }

    protected function customUserMessage(): string
    {
        return __('validation.custom.checkout.transaction_not_found');
    }

    /**
     * @return array<string, mixed>
     */
    protected function customMetadata(): array
    {
        return ['reference' => $this->reference];
    }
}
