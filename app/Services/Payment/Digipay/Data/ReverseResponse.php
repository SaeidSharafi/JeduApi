<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay\Data;

final readonly class ReverseResponse
{
    public function __construct(
        public string $trackingCode,
        public ?string $rrn,
        public ?string $maskedPan,
        public int $amount,
        public int $paymentGateway,
        public int $statusCode,
        public string $message,
    ) {}

    public static function fromResponse(array $response): self
    {
        return new self(
            trackingCode: $response['trackingCode'] ?? '',
            rrn: $response['rrn']                   ?? null,
            maskedPan: $response['maskedPan']       ?? null,
            amount: (int) ($response['amount'] ?? 0),
            paymentGateway: (int) ($response['paymentGateway'] ?? 0),
            statusCode: (int) ($response['result']['status'] ?? -1),
            message: $response['result']['message'] ?? '',
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === 0;
    }
}
