<?php

declare(strict_types=1);

use App\Enums\System\CacheKeysEnum;

describe('CacheKeysEnum', function (): void {
    it('can generate cache key without placeholders', function (): void {
        $cacheKey = CacheKeysEnum::Slider;
        expect($cacheKey->key())->toBe('shop.homepage.sliders');
    });

    it('can generate cache key with placeholders', function (): void {
        $cacheKey = CacheKeysEnum::UserProfile;

        expect($cacheKey->key(['id' => 123]))->toBe('user.123.profile');
    });

    it('returns correct TTL for each cache key', function (): void {
        $cacheKey = CacheKeysEnum::Slider;
        expect($cacheKey->ttl())->toBe(7200);
    });
});
