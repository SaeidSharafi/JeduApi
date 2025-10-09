<?php

declare(strict_types=1);

use App\Data\Admin\MediaData;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;
use SmartCache\Facades\SmartCache;

test('it retrieves an existing setting from the database', function (): void {
    // Arrange: Create a setting in our fresh, empty database.
    Setting::factory()->create([
        'key'   => SettingKeyEnum::HEADER->value,
        'value' => ['name' => 'Jedu Platform'],
    ]);
    $service = new SettingsService();

    // Act: Call the service.
    $value = $service->get(SettingKeyEnum::HEADER);

    // Assert: We got the correct value.
    expect($value)->toBe(['name' => 'Jedu Platform']);
});

test('it returns a default value when a setting does not exist', function (): void {
    // Arrange: The database is empty.
    $service = new SettingsService();

    // Act: Ask for a key that doesn't exist in the database, providing a default.
    $value = $service->get(SettingKeyEnum::HEADER, ['default' => 'value']);

    // Assert: We got our default value back.
    expect($value)->toBe(['default' => 'value']);
});

test('it hits the database only once and then uses the cache', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => 'value1']);
    $service = new SettingsService();
    DB::enableQueryLog();

    $service->get(SettingKeyEnum::HEADER);

    expect(Cache::has('settings.all'))->toBeTrue();

    $service->get(SettingKeyEnum::HEADER);

    $queryCount = collect(DB::getQueryLog())->filter(
        fn ($query): bool => str_contains($query['query'], 'select * from "settings"')
    )->count();

    expect($queryCount)->toBe(1);
});

test('the forget method clears the cache and forces a new database read', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => 'Jedu']);
    $service = new SettingsService();

    $service->get(SettingKeyEnum::HEADER);

    expect(Cache::has('settings.all'))->toBeTrue();

    DB::enableQueryLog(); // Start counting queries now.

    $service->forget();

    expect(SmartCache::has('settings.all'))->toBeFalse();

    $service->get(SettingKeyEnum::HEADER);

    $queryCount = collect(DB::getQueryLog())->filter(
        fn ($query): bool => str_contains($query['query'], 'select * from "settings"')
    )->count();

    expect($queryCount)->toBe(1);
});

test('it calls the Setting::witImages method for array values', function (): void {
    Storage::fake('public');
    $logo = MediaUploader::fromSource(UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();

    $setting = Setting::factory()->create([
        'key'   => SettingKeyEnum::FOOTER->value,
        'value' => ['logo' => $logo->id, 'links' => []],
    ]);
    $setting->attachMedia($logo, 'logo');
    $service = new SettingsService();

    // Act: Call the get method.
    $value = $service->get(SettingKeyEnum::FOOTER);
    expect($value['logo'])->toBeInstanceOf(MediaData::class)
        ->and($value['logo']->toArray())->toBe([
            'id'        => $logo->id,
            'url'       => $logo->getUrl(),
            'size'      => $logo->size,
            'file_name' => $logo->filename,
            'alt'       => $logo->getAttribute('alt'),
            'mime_type' => $logo->mime_type,
            'extension' => $logo->extension,
            'tag'       => null,
            'thumbnail' => null,
        ])
        ->and($value['links'])->toBeArray();

});
