<?php

use App\Data\Admin\MediaData;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;
use SmartCache\Facades\SmartCache;

test('it retrieves an existing setting from the database', function () {
    // Arrange: Create a setting in our fresh, empty database.
    Setting::factory()->create([
        'key'   => 'site_name',
        'value' => ['name' => 'Jedu Platform'],
    ]);
    $service = new SettingsService();

    // Act: Call the service.
    $value = $service->get('site_name');

    // Assert: We got the correct value.
    expect($value)->toBe(['name' => 'Jedu Platform']);
});

test('it returns a default value when a setting does not exist', function () {
    // Arrange: The database is empty.
    $service = new SettingsService();

    // Act: Ask for a key that doesn't exist.
    $value = $service->get('non_existent_key', ['default' => 'value']);

    // Assert: We got our default value back.
    expect($value)->toBe(['default' => 'value']);
});

test('it hits the database only once and then uses the cache', function () {
    // This entire test runs with its own fresh, empty cache and database.

    // Arrange
    Setting::factory()->create(['key' => 'setting_one', 'value' => 'value1']);
    $service = new SettingsService();
    DB::enableQueryLog();

    // Act 1: The first call. This should run a DB query and populate the cache.
    $service->get('setting_one');

    // Assert 1: The cache should now have our settings in it.
    expect(Cache::has('settings.all'))->toBeTrue();

    // Act 2: The second call. This should NOT run a DB query.
    $service->get('setting_one');

    // Assert 2: We prove it used the cache because only ONE query ever ran.
    $queryCount = collect(DB::getQueryLog())->filter(
        fn($query) => str_contains($query['query'], 'select * from "settings"')
    )->count();

    expect($queryCount)->toBe(1);
});

test('the forget method clears the cache and forces a new database read', function () {
    // This test also has its own fresh, empty cache and database.

    // Arrange:
    // 1. Create a setting.
    Setting::factory()->create(['key' => 'site_name', 'value' => 'Jedu']);
    $service = new SettingsService();

    // 2. Warm up the cache by calling get() once.
    $service->get('site_name');

    // 3. Sanity check: make sure the cache is actually populated.
    expect(Cache::has('settings.all'))->toBeTrue();

    DB::enableQueryLog(); // Start counting queries now.

    // Act:
    // 1. Forget the cache.
    $service->forget();

    expect(SmartCache::has('settings.all'))->toBeFalse();

    // 2. Request the setting again.
    $service->get('site_name');

    // Because the cache was gone, the second call to get() MUST have run a query to rebuild it.
    $queryCount = collect(DB::getQueryLog())->filter(
        fn($query) => str_contains($query['query'], 'select * from "settings"')
    )->count();

    expect($queryCount)->toBe(1);
});

test('it calls the Setting::witImages method for array values', function () {
    Storage::fake('public');
    $logo = MediaUploader::fromSource(UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();;

    $setting = Setting::factory()->create([
        'key'   => 'footer_settings',
        'value' => ['logo' => $logo->id, 'links' => []],
    ]);
    $setting->attachMedia($logo, 'logo');
    $service = new SettingsService();

    // Act: Call the get method.
    $value = $service->get('footer_settings');
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
