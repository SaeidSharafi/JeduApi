<?php

declare(strict_types=1);

use App\Data\Admin\MediaData;
use App\Enums\System\SettingKeyEnum;
use App\Models\AdminActionLog;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\SettingSecretRedactor;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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

test('set() persists value and invalidates cache', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => ['name' => 'Old']]);
    $service = new SettingsService();

    // Warm the cache.
    $service->get(SettingKeyEnum::HEADER);
    expect(Cache::has('settings.all'))->toBeTrue();

    // Act: set() should persist and bust cache.
    $service->set(SettingKeyEnum::HEADER, ['name' => 'New']);

    expect(Cache::has('settings.all'))->toBeFalse();
});

test('get() returns updated value after set() invalidates cache', function (): void {
    Setting::factory()->create(['key' => SettingKeyEnum::HEADER->value, 'value' => ['name' => 'Old']]);
    $service = new SettingsService();

    // Warm cache with old value.
    $first = $service->get(SettingKeyEnum::HEADER);
    expect($first)->toBe(['name' => 'Old']);

    // Update via set().
    $service->set(SettingKeyEnum::HEADER, ['name' => 'New']);

    // Subsequent get() must return new value.
    $second = $service->get(SettingKeyEnum::HEADER);
    expect($second)->toBe(['name' => 'New']);
});

test('integration keys skip witImages media lookup', function (): void {
    // Store a value that looks like it has a numeric field (would trigger witImages on non-integration keys).
    Setting::factory()->create([
        'key'   => SettingKeyEnum::IMS->value,
        'value' => ['url' => 'https://ims.example.com', 'token' => 'secret123', 'course_id' => 42],
    ]);
    $service = new SettingsService();

    $value = $service->get(SettingKeyEnum::IMS);

    // Raw array returned — no MediaData substitution attempted.
    expect($value)->toBeArray()
        ->and($value['url'])->toBe('https://ims.example.com')
        ->and($value['token'])->toBe('secret123')
        ->and($value['course_id'])->toBe(42);
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

// ─── Encryption tests ────────────────────────────────────────────────────────

test('set() encrypts secret fields for MOODLE before storing in DB', function (): void {
    $service = new SettingsService();

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

    $service = new SettingsService();
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

    $service = new SettingsService();
    $value   = $service->get(SettingKeyEnum::MOODLE);

    expect($value['token'])->toBe('legacy-plain-token')
        ->and($value['auth_userkey_token'])->toBe('legacy-plain-userkey');
});

test('set() encrypts all secret fields for BIG_BLUE_BUTTON', function (): void {
    $service = new SettingsService();

    $service->set(SettingKeyEnum::BIG_BLUE_BUTTON, [
        'enabled'                    => false,
        'base_url'                   => 'https://bbb.example.com',
        'secret'                     => 'bbb-secret',
        'default_attendee_password'  => 'attendee-pass',
        'default_moderator_password' => 'moderator-pass',
    ]);

    $raw    = DB::table('settings')->where('key', 'big_blue_button')->value('value');
    $stored = json_decode($raw, true);

    expect(Crypt::decryptString($stored['secret']))->toBe('bbb-secret')
        ->and(Crypt::decryptString($stored['default_attendee_password']))->toBe('attendee-pass')
        ->and(Crypt::decryptString($stored['default_moderator_password']))->toBe('moderator-pass');
});

test('set() does not encrypt empty secret fields', function (): void {
    $service = new SettingsService();

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
    $service = new SettingsService();

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
    $service = new SettingsService();

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
    $service = new SettingsService();

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
    $service = new SettingsService();

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

test('set() audit log redacts all BBB secret fields', function (): void {
    $staff   = Staff::factory()->create();
    $service = new SettingsService();

    $this->actingAs($staff, 'staff');

    $service->set(SettingKeyEnum::BIG_BLUE_BUTTON, [
        'enabled'                    => true,
        'base_url'                   => 'https://bbb.example.com',
        'secret'                     => 'bbb-secret',
        'default_attendee_password'  => 'attendee-pass',
        'default_moderator_password' => 'moderator-pass',
    ]);

    $log = AdminActionLog::where('route_name', 'settings.integration.big_blue_button')->firstOrFail();

    expect($log->request_data['value']['secret'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['default_attendee_password'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['default_moderator_password'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['base_url'])->toBe('https://bbb.example.com');
});

test('set() does NOT create audit log for non-integration keys', function (): void {
    $staff   = Staff::factory()->create();
    $service = new SettingsService();

    $this->actingAs($staff, 'staff');

    $service->set(SettingKeyEnum::HEADER, ['name' => 'Jedu']);

    expect(AdminActionLog::where('resource_type', 'integration_setting')->count())->toBe(0);
});

// ─── Redaction placeholder tests ─────────────────────────────────────────────

test('set() with explicit secret value encrypts and stores it', function (): void {
    $service = new SettingsService();

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
    $service = new SettingsService();

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
    $service = new SettingsService();

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
