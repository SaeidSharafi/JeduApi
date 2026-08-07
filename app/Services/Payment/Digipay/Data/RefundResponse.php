<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay\Data;

/**
 * DTO for refund response.
 */
final readonly class RefundResponse
{
    public function __construct(
        public int $statusCode,
        public string $message,
        public ?string $title = null,
        public ?string $level = null,
        public ?string $trackingCode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $result = $response['result'] ?? [];

        return new self(
            statusCode: (int) ($result['status'] ?? -1),
            message: (string) ($result['message'] ?? ''),
            title: $result['title']                 ?? null,
            level: $result['level']                 ?? null,
            trackingCode: $response['trackingCode'] ?? null,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === 0;
    }
}
