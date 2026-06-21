<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay;

use RuntimeException;
use Throwable;

final class DigipayException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $digipayCode = 0,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getDigipayCode(): int
    {
        return $this->digipayCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
