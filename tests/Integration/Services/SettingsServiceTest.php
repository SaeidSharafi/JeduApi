<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Data\Admin\MediaData;
use App\Enums\System\CacheKey;
use App\Enums\System\SettingKeyEnum;
use App\Models\AdminActionLog;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\SettingSecretRedactor;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

covers(SettingsService::class);

test('it retrieves an existing setting from the database', function (): void {
    // Arrange: Create a setting in our fresh, empty database.
    Setting::factory()->create([
        'key'   => SettingKeyEnum::HEADER->value,
        'value' => ['name' => 'Jedu Platform'],
    ]);
    $service = app(SettingsService::class);

    // Act: Call the service.
    $value = $service->get(SettingKeyEnum::HEADER);

    // Assert: We got the correct value.
    expect($value)->toBe(['name' => 'Jedu Platform']);
});

test('it returns a default value when a setting does not exist', function (): void {
    // Arrange: The database is empty.
    $service = app(SettingsService::class);

    // Act: Ask for a key that doesn't exist in the database, providing a default.
    $value = $service->get(SettingKeyEnum::HEADER, ['default' => 'value']);

    // Assert: We got our default value back.
    expect($value)->toBe(['default' => 'value']);
});

test('it hits the database only once and then uses the cache', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => 'value1']);
    $service = app(SettingsService::class);
    DB::enableQueryLog();

    $service->get(SettingKeyEnum::HEADER);
    $service->get(SettingKeyEnum::HEADER);

    $queryCount = collect(DB::getQueryLog())->filter(
        fn ($query): bool => str_contains($query['query'], 'select * from "settings"')
    )->count();

    expect($queryCount)->toBe(1);
});

test('the forget method clears the cache and forces a new database read', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => 'Jedu']);
    $service = app(SettingsService::class);

    $service->get(SettingKeyEnum::HEADER);

    DB::enableQueryLog(); // Start counting queries now.

    $service->forget();
    $service->get(SettingKeyEnum::HEADER);

    $queryCount = collect(DB::getQueryLog())->filter(
        fn ($query): bool => str_contains($query['query'], 'select * from "settings"')
    )->count();

    expect($queryCount)->toBe(1);
});

test('set() persists value and invalidates cache', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => ['name' => 'Old']]);
    $service = app(SettingsService::class);

    // Warm the cache.
    $service->get(SettingKeyEnum::HEADER);

    // Act: set() should persist and bust cache.
    $service->set(SettingKeyEnum::HEADER, ['name' => 'New']);

    DB::enableQueryLog();

    expect($service->get(SettingKeyEnum::HEADER))->toBe(['name' => 'New']);

    $queryCount = collect(DB::getQueryLog())->filter(
        fn ($query): bool => str_contains($query['query'], 'select * from "settings"')
    )->count();

    expect($queryCount)->toBe(1);
});

test('get() returns updated value after set() invalidates cache', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => ['name' => 'Old']]);
    $service = app(SettingsService::class);

    // Warm cache with old value.
    $first = $service->get(SettingKeyEnum::HEADER);
    expect($first)->toBe(['name' => 'Old']);

    // Update via set().
    $service->set(SettingKeyEnum::HEADER, ['name' => 'New']);

    // Subsequent get() must return new value.
    $second = $service->get(SettingKeyEnum::HEADER);
    expect($second)->toBe(['name' => 'New']);
});

test('set() clears the cached Digipay access token when Digipay settings are written', function (): void {
    $cache = app(CacheStore::class);
    $cache->put(CacheKey::DigipayAccessToken, [], 'token-minted-with-old-credentials');

    app(SettingsService::class)->set(SettingKeyEnum::DIGIPAY, ['client_id' => 'rotated-client']);

    expect($cache->get(CacheKey::DigipayAccessToken))->toBeNull();
});

test('set() keeps the cached Digipay access token when unrelated settings are written', function (): void {
    $cache = app(CacheStore::class);
    $cache->put(CacheKey::DigipayAccessToken, [], 'still-valid-token');

    app(SettingsService::class)->set(SettingKeyEnum::HEADER, ['name' => 'New']);

    expect($cache->get(CacheKey::DigipayAccessToken))->toBe('still-valid-token');
});

test('integration keys skip witImages media lookup', function (): void {
    // Store a value that looks like it has a numeric field (would trigger witImages on non-integration keys).
    Setting::factory()->create([
        'key'   => SettingKeyEnum::IMS->value,
        'value' => ['url' => 'https://ims.example.com', 'token' => 'secret123', 'course_id' => 42],
    ]);
    $service = app(SettingsService::class);

    $value = $service->get(SettingKeyEnum::IMS);

    // Raw array returned — no MediaData substitution attempted.
    expect($value)->toBeArray()
        ->and($value['url'])->toBe('https://ims.example.com')
        ->and($value['token'])->toBe('secret123')
        ->and($value['course_id'])->toBe(42);
});

test('skyroom credentials are not passed through the media resolver', function (): void {
    // A numeric "image" key would be hydrated into a MediaData DTO by witImages.
    Setting::factory()->create([
        'key'   => SettingKeyEnum::SKYROOM->value,
        'value' => ['enabled' => true, 'base_url' => 'https://www.skyroom.online/skyroom/api', 'api_key' => 'skyroom-key', 'image' => 7],
    ]);
    $service = app(SettingsService::class);

    $value = $service->get(SettingKeyEnum::SKYROOM);

    expect($value['image'])->toBe(7)
        ->and($value['api_key'])->toBe('skyroom-key')
        ->and($value['base_url'])->toBe('https://www.skyroom.online/skyroom/api');
});

test('payment gateway settings still resolve their icon through the media resolver', function (string $key, array $value): void {
    Setting::factory()->create(['key' => $key, 'value' => $value]);
    $service = app(SettingsService::class);

    // getValue() resolves media unconditionally — the stored setting must hydrate the
    // icon for the payment-gateway endpoints, which wrap it as a MediaData DTO.
    $expected = Setting::getValue(SettingKeyEnum::from($key));

    expect($service->get(SettingKeyEnum::from($key)))->toBe($expected);
})->with([
    'mellat'  => ['payments.mellat', ['enabled' => true, 'password' => 'mellat-pass', 'icon' => 7]],
    'digipay' => ['payments.digipay', ['enabled' => true, 'client_secret' => 'digipay-secret', 'password' => 'digipay-pass', 'icon' => 9]],
]);

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
    $service = app(SettingsService::class);

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

// ─── Encryption tests ────────────────────────────────────────────────────────

test('set() encrypts secret fields for MOODLE before storing in DB', function (): void {
    $service = app(SettingsService::class);

    $service->set(SettingKeyEnum::MOODLE, [
        'enabled'            => false,
        'base_url'           => 'https://moodle.example.com',
        'token'              => 'plain-token',
        'auth_userkey_token' => 'plain-userkey',
    ]);

    $raw    = DB::table('settings')->where('key', 'moodle')->value('value');
    $stored = json_decode($raw, true);

    // Stored values must NOT be plaintext.
    expect($stored['token'])->not->toBe('plain-token')
        ->and($stored['auth_userkey_token'])->not->toBe('plain-userkey')
        // But must be decryptable back to originals.
        ->and(Crypt::decryptString($stored['token']))->toBe('plain-token')
        ->and(Crypt::decryptString($stored['auth_userkey_token']))->toBe('plain-userkey')
        // Non-secret fields pass through unchanged.
        ->and($stored['base_url'])->toBe('https://moodle.example.com');
});

test('get() decrypts secret fields for MOODLE transparently', function (): void {
    // Store with encrypted token directly.
    Setting::factory()->create([
        'key'   => SettingKeyEnum::MOODLE->value,
        'value' => [
            'enabled'            => false,
            'base_url'           => 'https://moodle.example.com',
            'token'              => Crypt::encryptString('my-secret-token'),
            'auth_userkey_token' => Crypt::encryptString('my-userkey-token'),
        ],
    ]);

    $service = app(SettingsService::class);
    $value   = $service->get(SettingKeyEnum::MOODLE);

    expect($value['token'])->toBe('my-secret-token')
        ->and($value['auth_userkey_token'])->toBe('my-userkey-token')
        ->and($value['base_url'])->toBe('https://moodle.example.com');
});

test('get() returns plaintext legacy secret fields without error (backward compatibility)', function (): void {
    // Simulate legacy row with plaintext secrets (pre-encryption migration).
    Setting::factory()->create([
        'key'   => SettingKeyEnum::MOODLE->value,
        'value' => [
            'enabled'            => false,
            'base_url'           => 'https://moodle.example.com',
            'token'              => 'legacy-plain-token',
            'auth_userkey_token' => 'legacy-plain-userkey',
        ],
    ]);

    $service = app(SettingsService::class);
    $value   = $service->get(SettingKeyEnum::MOODLE);

    expect($value['token'])->toBe('legacy-plain-token')
        ->and($value['auth_userkey_token'])->toBe('legacy-plain-userkey');
});

test('set() encrypts all secret fields for SKYROOM', function (): void {
    $service = app(SettingsService::class);

    $service->set(SettingKeyEnum::SKYROOM, [
        'enabled'  => false,
        'base_url' => 'https://www.skyroom.online/skyroom/api',
        'api_key'  => 'skyroom-key',
        'secret'   => 'skyroom-secret',
    ]);

    $raw    = DB::table('settings')->where('key', 'skyroom')->value('value');
    $stored = json_decode($raw, true);

    expect(Crypt::decryptString($stored['api_key']))->toBe('skyroom-key')
        ->and(Crypt::decryptString($stored['secret']))->toBe('skyroom-secret');
});

test('set() does not encrypt empty secret fields', function (): void {
    $service = app(SettingsService::class);

    $service->set(SettingKeyEnum::MOODLE, [
        'enabled'            => false,
        'base_url'           => 'https://moodle.example.com',
        'token'              => '',
        'auth_userkey_token' => 'real-userkey',
    ]);

    $raw    = DB::table('settings')->where('key', 'moodle')->value('value');
    $stored = json_decode($raw, true);

    // Empty string stays empty (not encrypted).
    expect($stored['token'])->toBe('')
        ->and(Crypt::decryptString($stored['auth_userkey_token']))->toBe('real-userkey');
});

test('set() and get() round-trip encrypts and decrypts IMS api_key', function (): void {
    $service = app(SettingsService::class);

    $service->set(SettingKeyEnum::IMS, [
        'enabled'  => true,
        'base_url' => 'https://ims.example.com',
        'api_key'  => 'ims-secret-key',
    ]);

    $value = $service->get(SettingKeyEnum::IMS);

    expect($value['api_key'])->toBe('ims-secret-key')
        ->and($value['base_url'])->toBe('https://ims.example.com');
});

// ─── Audit logging tests ──────────────────────────────────────────────────────

test('set() creates an audit log entry when an integration key is written', function (): void {
    $staff   = Staff::factory()->create();
    $service = app(SettingsService::class);

    $this->actingAs($staff, 'staff');

    $service->set(SettingKeyEnum::MOODLE, [
        'enabled'  => true,
        'base_url' => 'https://moodle.example.com',
        'token'    => 'super-secret-token',
    ]);

    expect(AdminActionLog::where('route_name', 'settings.integration.moodle')->count())->toBe(1);
});

test('set() audit log does not contain secret values for integration keys', function (): void {
    $staff   = Staff::factory()->create();
    $service = app(SettingsService::class);

    $this->actingAs($staff, 'staff');

    $service->set(SettingKeyEnum::MOODLE, [
        'enabled'            => true,
        'base_url'           => 'https://moodle.example.com',
        'token'              => 'super-secret-token',
        'auth_userkey_token' => 'another-secret',
    ]);

    $log = AdminActionLog::where('route_name', 'settings.integration.moodle')->firstOrFail();

    expect($log->request_data['value']['token'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['auth_userkey_token'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['base_url'])->toBe('https://moodle.example.com')
        ->and($log->request_data['key'])->toBe('moodle');
});

test('set() audit log records the acting staff id', function (): void {
    $staff   = Staff::factory()->create();
    $service = app(SettingsService::class);

    $this->actingAs($staff, 'staff');

    $service->set(SettingKeyEnum::SPOT_PLAYER, [
        'enabled' => true,
        'api_key' => 'spot-secret',
    ]);

    $log = AdminActionLog::where('route_name', 'settings.integration.spot_player')->firstOrFail();

    expect($log->admin_id)->toBe($staff->id)
        ->and($log->risk_level)->toBe('high')
        ->and($log->action_type)->toBe('update');
});

test('set() audit log redacts every SKYROOM secret field', function (): void {
    $staff   = Staff::factory()->create();
    $service = app(SettingsService::class);

    $this->actingAs($staff, 'staff');

    $service->set(SettingKeyEnum::SKYROOM, [
        'enabled'  => true,
        'base_url' => 'https://www.skyroom.online/skyroom/api',
        'api_key'  => 'skyroom-key',
        'secret'   => 'skyroom-secret',
    ]);

    $log = AdminActionLog::where('route_name', 'settings.integration.skyroom')->firstOrFail();

    expect($log->request_data['value']['api_key'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['secret'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['base_url'])->toBe('https://www.skyroom.online/skyroom/api');
});

test('set() does NOT create audit log for non-integration keys', function (): void {
    $staff   = Staff::factory()->create();
    $service = app(SettingsService::class);

    $this->actingAs($staff, 'staff');

    $service->set(SettingKeyEnum::HEADER, ['name' => 'Jedu']);

    expect(AdminActionLog::where('resource_type', 'integration_setting')->count())->toBe(0);
});

// ─── Redaction placeholder tests ─────────────────────────────────────────────

test('set() with explicit secret value encrypts and stores it', function (): void {
    $service = app(SettingsService::class);

    $service->set(SettingKeyEnum::IMS, [
        'enabled'  => true,
        'base_url' => 'https://ims.example.com',
        'api_key'  => 'real-secret-key',
    ]);

    $raw    = DB::table('settings')->where('key', 'ims')->value('value');
    $stored = json_decode($raw, true);

    expect(Crypt::decryptString($stored['api_key']))->toBe('real-secret-key');
});

test('set() with placeholder value preserves existing stored secret', function (): void {
    $service = app(SettingsService::class);

    // First: store a real secret.
    $service->set(SettingKeyEnum::IMS, [
        'enabled'  => true,
        'base_url' => 'https://ims.example.com',
        'api_key'  => 'original-secret',
    ]);

    // Second: submit payload with redaction placeholder.
    $service->set(SettingKeyEnum::IMS, [
        'enabled'  => false,
        'base_url' => 'https://ims-updated.example.com',
        'api_key'  => SettingSecretRedactor::REDACTED,
    ]);

    $raw    = DB::table('settings')->where('key', 'ims')->value('value');
    $stored = json_decode($raw, true);

    // Placeholder must NOT be stored.
    expect($stored['api_key'])->not->toBe(SettingSecretRedactor::REDACTED)
        // Non-secret field updated normally.
        ->and($stored['base_url'])->toBe('https://ims-updated.example.com');
});

test('get() returns original secret after placeholder submission', function (): void {
    $service = app(SettingsService::class);

    // Store real secret.
    $service->set(SettingKeyEnum::IMS, [
        'enabled'  => true,
        'base_url' => 'https://ims.example.com',
        'api_key'  => 'original-secret',
    ]);

    // Submit placeholder — should not clobber.
    $service->set(SettingKeyEnum::IMS, [
        'enabled'  => false,
        'base_url' => 'https://ims-updated.example.com',
        'api_key'  => SettingSecretRedactor::REDACTED,
    ]);

    $value = $service->get(SettingKeyEnum::IMS);

    expect($value['api_key'])->toBe('original-secret')
        ->and($value['base_url'])->toBe('https://ims-updated.example.com');
});
