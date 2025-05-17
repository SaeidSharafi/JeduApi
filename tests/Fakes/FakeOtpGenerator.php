<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\OtpGeneratorInterface;

final class FakeOtpGenerator implements OtpGeneratorInterface
{
    public int $nextOtpCode = 123456;

    public string $nextTrackingCode = 'fake-tracking-code';

    public array $otpCodeSequence = [];

    public array $trackingCodeSequence = [];

    public function generateCode(): int
    {
        if (! empty($this->otpCodeSequence)) {
            return array_shift($this->otpCodeSequence);
        }

        return $this->nextOtpCode;
    }

    public function generateTrackingCode(): string
    {
        if (! empty($this->trackingCodeSequence)) {
            return array_shift($this->trackingCodeSequence);
        }

        return $this->nextTrackingCode;
    }

    // Methods to control the fake generator from your tests
    public function setNextOtpCode(int $code): self
    {
        $this->nextOtpCode = $code;

        return $this;
    }

    public function setNextTrackingCode(string $code): self
    {
        $this->nextTrackingCode = $code;

        return $this;
    }

    public function setOtpCodeSequence(array $codes): self
    {
        $this->otpCodeSequence = $codes;

        return $this;
    }
}
