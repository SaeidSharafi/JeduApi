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

    /**
     * Get user-friendly error message.
     */
    public function getUserMessage(): string
    {
        return DigipayPaymentStatus::getMessage($this->digipayCode);
    }

    /**
     * Convert to array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exception'  => self::class,
            'message'    => $this->getMessage(),
            'error_code' => $this->digipayCode,
            'context'    => $this->context,
            'file'       => $this->getFile(),
            'line'       => $this->getLine(),
        ];
    }
}
