<?php

declare(strict_types=1);

use App\Services\DefaultOtpGenerator;
use Illuminate\Support\Str;

describe('DefaultOtpGenerator', function (): void {
    beforeEach(function (): void {
        $this->generator = new DefaultOtpGenerator();
    });

    it('generates a code within the configured range', function (): void {
        config(['otp.code_min' => 1111, 'otp.code_max' => 9999]);
        $code = $this->generator->generateCode();
        expect($code)->toBeInt()
            ->and($code)->toBeGreaterThanOrEqual(1111)
            ->and($code)->toBeLessThanOrEqual(9999);
    });

    it('generates a unique tracking code as a valid UUID', function (): void {
        $trackingCode = $this->generator->generateTrackingCode();
        expect(Str::isUuid($trackingCode))->toBeTrue();
    });
});
