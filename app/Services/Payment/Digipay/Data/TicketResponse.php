<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay\Data;

final readonly class TicketResponse
{
    public function __construct(
        public string $redirectUrl,
        public string $ticket,
        public int $statusCode,
        public string $message,
    ) {}

    public static function fromResponse(array $response): self
    {
        return new self(
            redirectUrl: $response['redirectUrl'] ?? '',
            ticket: $response['ticket']           ?? '',
            statusCode: (int) ($response['result']['status'] ?? -1),
            message: $response['result']['message'] ?? '',
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === 0 && ! empty($this->redirectUrl);
    }
}
