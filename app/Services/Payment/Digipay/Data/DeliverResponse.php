<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay\Data;

final readonly class DeliverResponse
{
    public function __construct(
        public int $statusCode,
        public string $message,
        public ?string $level = null,
    ) {}

    public static function fromResponse(array $response): self
    {
        $result = $response['result'] ?? [];

        return new self(
            statusCode: (int) ($result['status'] ?? -1),
            message: (string) ($result['message'] ?? ''),
            level: $result['level'] ?? null,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === 0;
    }
}
