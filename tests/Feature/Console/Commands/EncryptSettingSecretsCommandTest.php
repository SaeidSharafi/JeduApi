<?php

declare(strict_types=1);

use App\Console\Commands\EncryptSettingSecretsCommand;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;

use function Pest\Laravel\artisan;

covers(EncryptSettingSecretsCommand::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Insert a raw setting row bypassing SettingsService encryption so we can
 * simulate a legacy plaintext payload.
 */
function insertPlaintextSetting(SettingKeyEnum $key, array $value): void
{
    // Bypass the Eloquent cast by writing raw JSON directly to the DB so we
    // can simulate legacy plaintext payloads without going through SettingsService.
    Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
        ['key' => $key->value],
        ['value'         => json_encode($value), 'type' => 'json', 'group' => null,
            'created_at' => now(), 'updated_at' => now()],
    );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('encrypts plaintext secret fields and leaves non-secret fields untouched', function (): void {
    insertPlaintextSetting(SettingKeyEnum::SKYROOM, [
        'base_url' => 'https://www.skyroom.online/skyroom/api',
        'api_key'  => 'my-plaintext-key',
        'secret'   => 'my-plaintext-secret',
    ]);

    artisan('settings:encrypt-secrets')
        ->assertExitCode(0);

    $raw    = Setting::where('key', SettingKeyEnum::SKYROOM->value)->first();
    $stored = $raw->value; // cast to array

    // Non-secret field unchanged.
    expect($stored['base_url'])->toBe('https://www.skyroom.online/skyroom/api');

    // Secret fields are now encrypted (not plaintext).
    expect($stored['api_key'])->not->toBe('my-plaintext-key');
    expect($stored['secret'])->not->toBe('my-plaintext-secret');

    // Decryptable via Crypt.
    expect(Crypt::decryptString($stored['api_key']))->toBe('my-plaintext-key');
    expect(Crypt::decryptString($stored['secret']))->toBe('my-plaintext-secret');
});

it('is idempotent — re-running does not double-encrypt already-encrypted values', function (): void {
    insertPlaintextSetting(SettingKeyEnum::SKYROOM, [
        'base_url' => 'https://www.skyroom.online/skyroom/api',
        'secret'   => 'my-plaintext-secret',
    ]);

    // First run.
    artisan('settings:encrypt-secrets')->assertExitCode(0);

    $afterFirst      = Setting::where('key', SettingKeyEnum::SKYROOM->value)->first()->value;
    $encryptedSecret = $afterFirst['secret'];

    // Second run.
    artisan('settings:encrypt-secrets')->assertExitCode(0);

    $afterSecond = Setting::where('key', SettingKeyEnum::SKYROOM->value)->first()->value;

    // Encrypted payload must be identical (not re-encrypted).
    expect($afterSecond['secret'])->toBe($encryptedSecret);

    // Still decryptable to original plaintext.
    expect(Crypt::decryptString($afterSecond['secret']))->toBe('my-plaintext-secret');
});

it('handles mixed payload — skips already-encrypted, encrypts remaining plaintext', function (): void {
    $alreadyEncrypted = Crypt::encryptString('pre-encrypted-token');

    insertPlaintextSetting(SettingKeyEnum::MOODLE, [
        'url'                => 'https://moodle.example.com',
        'token'              => 'plaintext-token',
        'auth_userkey_token' => $alreadyEncrypted,
    ]);

    artisan('settings:encrypt-secrets')->assertExitCode(0);

    $stored = Setting::where('key', SettingKeyEnum::MOODLE->value)->first()->value;

    // Plaintext token was encrypted.
    expect(Crypt::decryptString($stored['token']))->toBe('plaintext-token');

    // Pre-encrypted token was not double-encrypted.
    expect(Crypt::decryptString($stored['auth_userkey_token']))->toBe('pre-encrypted-token');
});

it('integration settings are readable via SettingsService::get() after migration', function (): void {
    insertPlaintextSetting(SettingKeyEnum::NILIROOM, [
        'base_url'  => 'https://niliroom.example.com',
        'api_token' => 'my-secret',
    ]);

    artisan('settings:encrypt-secrets')->assertExitCode(0);

    // Bust the SmartCache so SettingsService reads fresh DB data.
    app(SettingsService::class)->forget();

    $value = app(SettingsService::class)->get(SettingKeyEnum::NILIROOM);

    expect($value['base_url'])->toBe('https://niliroom.example.com');
    expect($value['api_token'])->toBe('my-secret');
});

it('skips settings that do not exist in the database', function (): void {
    // Ensure no IMS setting exists.
    Setting::where('key', SettingKeyEnum::IMS->value)->delete();

    artisan('settings:encrypt-secrets')
        ->expectsOutputToContain('no record in DB')
        ->assertExitCode(0);
});

it('dry-run does not write changes to the database', function (): void {
    insertPlaintextSetting(SettingKeyEnum::SPOT_PLAYER, [
        'api_key' => 'plaintext-api-key',
    ]);

    artisan('settings:encrypt-secrets --dry-run')
        ->expectsOutputToContain('DRY RUN')
        ->assertExitCode(0);

    $stored = Setting::where('key', SettingKeyEnum::SPOT_PLAYER->value)->first()->value;

    // Value must remain plaintext — dry-run wrote nothing.
    expect($stored['api_key'])->toBe('plaintext-api-key');
});
