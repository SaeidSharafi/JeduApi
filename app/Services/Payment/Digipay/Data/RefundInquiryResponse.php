<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay\Data;

final readonly class RefundInquiryResponse
{
    public function __construct(
        public int $statusCode,
        public string $message,
        public ?int $status = null,  // 0=completed, 1=failed, 2=pending
        public ?string $trackingCode = null,
        public ?string $transferDate = null,
        public ?int $destinationType = null,  // 0=masked PAN, 1=IBAN, 2=Wallet, 3=Credit
        public ?string $destination = null,
    ) {}

    public static function fromResponse(array $response): self
    {
        $result = $response['result'] ?? [];

        return new self(
            statusCode: (int) ($result['status'] ?? -1),
            message: (string) ($result['message'] ?? ''),
            status: isset($response['status']) ? (int) $response['status'] : null,
            trackingCode: $response['trackingCode'] ?? null,
            transferDate: $response['transferDate'] ?? null,
            destinationType: isset($response['destinationType']) ? (int) $response['destinationType'] : null,
            destination: $response['destination'] ?? null,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === 0;
    }

    public function isRefundCompleted(): bool
    {
        return $this->status === 0;
    }

    public function isRefundFailed(): bool
    {
        return $this->status === 1;
    }

    public function isRefundPending(): bool
    {
        return $this->status === 2;
    }
}
