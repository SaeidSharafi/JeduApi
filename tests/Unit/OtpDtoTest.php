<?php

declare(strict_types=1);

use App\Dto\OtpManager\OtpDto;

describe('OtpDto', function () {
    it('constructs and returns correct array', function () {
        $code = 123456;
        $trackingCode = 'track-abc-789';
        $dto = new OtpDto($code, $trackingCode);

        expect($dto->code)->toBe($code);
        expect($dto->trackingCode)->toBe($trackingCode);

        $array = $dto->toArray();
        expect($array)->toBe([
            'code' => $code,
            'tracking_code' => $trackingCode,
        ]);
    });

    it('handles edge cases for code and trackingCode', function () {
        $dto = new OtpDto(0, '');
        expect($dto->code)->toBe(0);
        expect($dto->trackingCode)->toBe('');
        expect($dto->toArray())->toBe([
            'code' => 0,
            'tracking_code' => '',
        ]);
    });
});
