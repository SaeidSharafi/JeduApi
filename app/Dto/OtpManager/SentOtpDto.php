<?php

declare(strict_types=1);

namespace App\Dto\OtpManager;

use App\Enums\OtpType;

class SentOtpDto
{
    public int $code;

    public ?OtpType $otpType;

    public string $trackingCode;

    private int $waitingTime;

    public function __construct(int $code, OtpType $otpType, int $waitingTime, string $trackingCode)
    {
        $this->code = $code;
        $this->otpType = $otpType;
        $this->waitingTime = $waitingTime;
        $this->trackingCode = $trackingCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'otpType' => $this->otpType,
            'tracking_code' => $this->trackingCode,
            'waiting_time' => $this->waitingTime,
        ];
    }
}
