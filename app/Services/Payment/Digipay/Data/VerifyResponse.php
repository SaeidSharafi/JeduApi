<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay\Data;

final readonly class VerifyResponse
{
    public function __construct(
        public string $trackingCode,
        public string $providerId,
        public int $amount,
        public ?string $rrn,
        public ?string $maskedPan,
        public ?string $pspName,
        public ?string $terminalId,
        public int $paymentGateway,
        public int $statusCode,
        public string $message,
    ) {}

    public static function fromResponse(array $response): self
    {
        return new self(
            trackingCode: $response['trackingCode'] ?? '',
            providerId: $response['providerId']     ?? '',
            amount: (int) ($response['amount'] ?? 0),
            rrn: $response['rrn']               ?? null,
            maskedPan: $response['maskedPan']   ?? null,
            pspName: $response['pspName']       ?? null,
            terminalId: $response['terminalId'] ?? null,
            paymentGateway: (int) ($response['paymentGateway'] ?? 0),
            statusCode: (int) ($response['result']['status'] ?? -1),
            message: $response['result']['message'] ?? '',
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === 0;
    }

    public function toTransactionData(): array
    {
        return [
            'tracking_code'   => $this->trackingCode,
            'provider_id'     => $this->providerId,
            'amount'          => $this->amount,
            'rrn'             => $this->rrn,
            'masked_pan'      => $this->maskedPan,
            'psp_name'        => $this->pspName,
            'terminal_id'     => $this->terminalId,
            'payment_gateway' => $this->paymentGateway,
        ];
    }
}
