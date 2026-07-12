<?php

declare(strict_types=1);

namespace App\Exceptions\Gateway;

use App\Services\Payment\Digipay\DigipayPaymentStatus;
use Throwable;

final class DigipayException extends BankException
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

    public function errorCode(): string
    {
        return 'DIGIPAY_ERROR_'.$this->digipayCode;
    }

    protected function customUserMessage(): string
    {
        return $this->getUserMessage();
    }

    protected function customMetadata(): array
    {
        return $this->context;
    }
}
