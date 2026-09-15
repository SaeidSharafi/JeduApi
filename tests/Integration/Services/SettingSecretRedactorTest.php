<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Models\AdminActionLog;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\SettingSecretRedactor;
use App\Services\SettingsService;

covers(SettingSecretRedactor::class);

// This file's mutation scope is the redactor registry alone: SettingsService's own
// register/skip/audit behavior is exercised (and mutated) by SettingsServiceTest.

/**
 * Every secret field of every integration setting, with a representative
 * plaintext value. Payment gateways are excluded: their secrets live under a
 * nested config object, which is out of this registry's scope.
 *
 * @return array<string, array{0: SettingKeyEnum, 1: string, 2: string}>
 */
function declaredIntegrationSecrets(): array
{
    $datasets = [];

    foreach (SettingKeyEnum::cases() as $key) {
        if (! $key->hasSecrets() || str_starts_with($key->value, 'payment.')) {
            continue;
        }

        foreach ($key->secretFields() as $field) {
            $datasets["{$key->value}.{$field}"] = [$key, $field, "plain-{$key->value}-{$field}"];
        }
    }

    return $datasets;
}

it('redacts every field an integration setting declares as a secret', function (SettingKeyEnum $key, string $field, string $plaintext): void {
    $redacted = app(SettingSecretRedactor::class)->redact($key->value, [
        'enabled'  => true,
        $field     => $plaintext,
        'base_url' => 'https://example.com',
    ]);

    expect($redacted[$field])->toBe(SettingSecretRedactor::REDACTED, "{$key->value}.{$field} was not redacted")
        ->and($redacted['base_url'])->toBe('https://example.com');
})->with(fn (): array => declaredIntegrationSecrets());

it('redacts every declared secret of a stored setting through the read path', function (SettingKeyEnum $key, string $field, string $plaintext): void {
    Setting::factory()->create(['key' => $key->value, 'value' => ['enabled' => true, $field => $plaintext]]);

    $stored   = (new SettingsService())->get($key);
    $redacted = app(SettingSecretRedactor::class)->redact($key->value, $stored);

    expect($redacted[$field])->toBe(SettingSecretRedactor::REDACTED);
})->with(fn (): array => declaredIntegrationSecrets());

it('audit logs a skyroom write with its secrets redacted', function (): void {
    $staff = Staff::factory()->create();

    $this->actingAs($staff, 'staff');

    (new SettingsService())->set(SettingKeyEnum::SKYROOM, [
        'enabled'  => true,
        'base_url' => 'https://www.skyroom.online/skyroom/api',
        'api_key'  => 'skyroom-real-key',
        'secret'   => 'skyroom-real-secret',
    ]);

    $log = AdminActionLog::where('route_name', 'settings.integration.skyroom')->firstOrFail();

    expect($log->request_data['value']['api_key'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['secret'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($log->request_data['value']['base_url'])->toBe('https://www.skyroom.online/skyroom/api');
});

it('a setting with no declared secret fields passes through unchanged', function (): void {
    $value = app(SettingSecretRedactor::class)->redact('contact_info', [
        'support_email' => 'test@example.com',
        'phone'         => '02100000000',
    ]);

    expect($value)->toBe([
        'support_email' => 'test@example.com',
        'phone'         => '02100000000',
    ]);
});

it('redacts a declared secret field even when its stored value is null', function (): void {
    $value = app(SettingSecretRedactor::class)->redact('skyroom', [
        'enabled' => true,
        'api_key' => null,
    ]);

    expect($value['api_key'])->toBe(SettingSecretRedactor::REDACTED);
});

it('passes a non-array setting value through untouched', function (): void {
    expect(app(SettingSecretRedactor::class)->redact('skyroom', 'plain-string'))->toBe('plain-string')
        ->and(app(SettingSecretRedactor::class)->redact('skyroom', null))->toBeNull();
});

it('reports which setting keys declare secrets', function (): void {
    $redactor = app(SettingSecretRedactor::class);

    expect($redactor->hasSecrets('skyroom'))->toBeTrue()
        ->and($redactor->hasSecrets('niliroom'))->toBeTrue()
        ->and($redactor->hasSecrets('sms.ippanel'))->toBeTrue()
        ->and($redactor->hasSecrets('contact_info'))->toBeFalse();
});
