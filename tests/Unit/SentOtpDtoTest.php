<?php

declare(strict_types=1);

use App\Dto\OtpManager\SentOtpDto;
use App\Enums\OtpType;

describe('SentOtpDto', function (): void {
    it('constructs and returns correct array', function (): void {
        $code = 654321;
        $otpType = OtpType::SIGNIN;
        $waitingTime = 120;
        $trackingCode = 'track-xyz-123';
        $dto = new SentOtpDto($code, $otpType, $waitingTime, $trackingCode);

        expect($dto->code)->toBe($code);
        expect($dto->otpType)->toBe($otpType);
        expect($dto->trackingCode)->toBe($trackingCode);

        $array = $dto->toArray();
        expect($array)->toBe([
            'code' => $code,
            'otpType' => $otpType,
            'tracking_code' => $trackingCode,
            'waiting_time' => $waitingTime,
        ]);
    });

    it('throws TypeError if otpType is not provided', function (): void {
        $code = 111111;
        $waitingTime = 0;
        $trackingCode = 'empty-track';
        $closure = fn (): SentOtpDto => new SentOtpDto($code, null, $waitingTime, $trackingCode);
        expect($closure)->toThrow(TypeError::class);
    });
});
