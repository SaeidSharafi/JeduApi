<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay\Data;

final readonly class CallbackPayload
{
    public function __construct(
        public int $amount,
        public string $providerId,
        public string $trackingCode,
        public string $result,
        public int $type,
        public ?string $rrn = null,
        public ?string $psp = null,
        public ?string $pspCode = null,
        public ?string $pspName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            amount: (int) ($data['amount'] ?? 0),
            providerId: $data['providerId']     ?? '',
            trackingCode: $data['trackingCode'] ?? '',
            result: $data['result']             ?? 'FAILURE',
            type: (int) ($data['type'] ?? 0),
            rrn: $data['rrn']         ?? null,
            psp: $data['psp']         ?? null,
            pspCode: $data['pspCode'] ?? null,
            pspName: $data['pspName'] ?? null,
        );
    }

    public function isSuccessful(): bool
    {
        return mb_strtoupper($this->result) === 'SUCCESS';
    }
}
