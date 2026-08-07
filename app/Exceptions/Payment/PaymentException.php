<?php

declare(strict_types=1);

namespace App\Exceptions\Payment;

use App\Contracts\Payment\PaymentExceptionContract;
use Exception;

abstract class PaymentException extends Exception implements PaymentExceptionContract
{
    abstract public function errorCode(): string;

    final public function userMessage(): string
    {
        return $this->customUserMessage() ?? $this->getMessage();
    }

    final public function metadata(): array
    {
        return $this->customMetadata();
    }

    protected function customUserMessage(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function customMetadata(): array
    {
        return [];
    }
}
