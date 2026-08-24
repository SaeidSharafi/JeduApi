<?php

declare(strict_types=1);

use App\Enums\System\CacheKeysEnum;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use SmartCache\Facades\SmartCache;

beforeEach(function (): void {
    SmartCache::clear();
});

describe('SettingObserver - Cache Invalidation', function (): void {
    it('clears the settings cache when a setting is updated', function (): void {
        // Arrange
        $setting = Setting::factory()->create([
            'key'   => SettingKeyEnum::HEADER->value,
            'value' => ['name' => 'Old'],
        ]);
        $service = new SettingsService();

        // Warm the cache with the old value.
        expect($service->get(SettingKeyEnum::HEADER))->toBe(['name' => 'Old']);
        expect(Cache::has(CacheKeysEnum::Settings->value))->toBeTrue();

        // Act
        $setting->update(['value' => ['name' => 'New']]);

        // Assert: cache forgotten, next read returns fresh value.
        expect(Cache::has(CacheKeysEnum::Settings->value))->toBeFalse();
        expect($service->get(SettingKeyEnum::HEADER))->toBe(['name' => 'New']);
    });

    it('clears the settings cache when a setting is created', function (): void {
        // Arrange
        $service = new SettingsService();

        // Warm the cache (empty settings collection) and confirm it is cached.
        expect($service->get(SettingKeyEnum::HEADER))->toBeNull();
        expect(Cache::has(CacheKeysEnum::Settings->value))->toBeTrue();

        // Act
        Setting::factory()->create([
            'key'   => SettingKeyEnum::HEADER->value,
            'value' => ['name' => 'Created'],
        ]);

        // Assert: cache forgotten, next read returns the created setting.
        expect(Cache::has(CacheKeysEnum::Settings->value))->toBeFalse();
        expect($service->get(SettingKeyEnum::HEADER))->toBe(['name' => 'Created']);
    });

    it('clears the settings cache when a setting is deleted', function (): void {
        // Arrange
        $setting = Setting::factory()->create([
            'key'   => SettingKeyEnum::HEADER->value,
            'value' => ['name' => 'Old'],
        ]);
        $service = new SettingsService();

        // Warm the cache.
        expect($service->get(SettingKeyEnum::HEADER))->toBe(['name' => 'Old']);
        expect(Cache::has(CacheKeysEnum::Settings->value))->toBeTrue();

        // Act
        $setting->delete();

        // Assert: cache forgotten, next read returns the default (no stale value).
        expect(Cache::has(CacheKeysEnum::Settings->value))->toBeFalse();
        expect($service->get(SettingKeyEnum::HEADER))->toBeNull();
    });
});
