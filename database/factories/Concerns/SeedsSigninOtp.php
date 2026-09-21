<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use App\Contracts\Cache\CacheStore;
use App\Data\OtpManager\OtpDto;
use App\Enums\System\CacheKey;
use App\Enums\System\OtpType;
use App\Services\OtpManagerService;

/**
 * Seeds a verifiable sign-in OTP through the same gateway the OTP service reads
 * from, so a factory-created account can complete an OTP login without the test
 * writing a cache entry by hand.
 */
trait SeedsSigninOtp
{
    protected function seedSigninOtp(string $phone, string $guard, int $code): void
    {
        $sentOtp = app(OtpManagerService::class)->send($phone, $guard, OtpType::SIGNIN);

        app(CacheStore::class)->put(
            CacheKey::OtpValue,
            OtpManagerService::cacheParams($phone, $guard, OtpType::SIGNIN),
            new OtpDto($code, $sentOtp->trackingCode),
        );
    }
}
