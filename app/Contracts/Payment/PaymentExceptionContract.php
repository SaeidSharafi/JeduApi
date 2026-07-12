<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

interface PaymentExceptionContract
{
    public function errorCode(): string;

    public function userMessage(): string;

    /** @return array<string, mixed> */
    public function metadata(): array;
}
