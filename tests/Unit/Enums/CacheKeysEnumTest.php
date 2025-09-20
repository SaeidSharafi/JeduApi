<?php

use App\Enums\CacheKeysEnum;

describe('CacheKeysEnum', function (): void {
    it('can generate cache key without placeholders', function (): void {
        $cacheKey = CacheKeysEnum::HomePageContent;
        expect($cacheKey->key())->toBe('shop.homepage.content');
    });

    it('can generate cache key with placeholders', function (): void {
        $cacheKey = CacheKeysEnum::UserProfile;

        expect($cacheKey->key(['id' => 123]))->toBe('user.123.profile');
    });

    it('returns correct TTL for each cache key', function (): void {
        $cacheKey = CacheKeysEnum::HomePageContent;
        expect($cacheKey->ttl())->toBe(3600);
    });
});
