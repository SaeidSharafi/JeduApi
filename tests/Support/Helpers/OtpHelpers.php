<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Data\OtpManager\OtpDto;
use App\Enums\System\CacheKey;
use App\Enums\System\OtpType;
use App\Services\OtpManagerService;

if (! function_exists('putCachedOtp')) {
    /**
     * Seed a verifiable OTP through the cache gateway, the same path the OTP
     * service reads it from. Passing a $sentAt timestamp also writes the send
     * marker, which is how an expired-but-present code is simulated.
     */
    function putCachedOtp(
        string $identifier,
        string $guard,
        OtpType $type,
        int $code,
        string $trackingCode,
        ?int $sentAt = null,
    ): void {
        $cache  = app(CacheStore::class);
        $params = OtpManagerService::cacheParams($identifier, $guard, $type);

        $cache->put(CacheKey::OtpValue, $params, new OtpDto($code, $trackingCode));

        if ($sentAt !== null) {
            $cache->put(CacheKey::OtpMarker, $params, $sentAt);
        }
    }
}
