<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);

use App\Enums\PermissionEnum;
use App\Models\Setting;
use App\Services\SettingSecretRedactor;

it('can get list of settings', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->count(3)->create();
    $response = $this->getJson(route('api.v1.admin.settings.index'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    '*' => [
                        'id',
                        'key',
                        'value',
                        'type',
                        'group',
                    ],
                ],
            ],
            'metadata',
        ]);
});

it('redacts ims api_key in index response', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->ims()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $response->assertStatus(200);

    $imsSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'ims');
    expect($imsSettings)->not->toBeNull()
        ->and($imsSettings['value']['api_key'])->toBe(SettingSecretRedactor::REDACTED);
});

it('redacts moodle token and auth_userkey_token in index response', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->moodle()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $response->assertStatus(200);

    $moodleSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'moodle');
    expect($moodleSettings)->not->toBeNull()
        ->and($moodleSettings['value']['token'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($moodleSettings['value']['auth_userkey_token'])->toBe(SettingSecretRedactor::REDACTED);
});

it('redacts big_blue_button secret and passwords in index response', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->bigBlueButton()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $response->assertStatus(200);

    $bbbSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'big_blue_button');
    expect($bbbSettings)->not->toBeNull()
        ->and($bbbSettings['value']['secret'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($bbbSettings['value']['default_attendee_password'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($bbbSettings['value']['default_moderator_password'])->toBe(SettingSecretRedactor::REDACTED);
});

it('redacts spot_player api_key in index response', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->spotPlayer()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $response->assertStatus(200);

    $spotSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'spot_player');
    expect($spotSettings)->not->toBeNull()
        ->and($spotSettings['value']['api_key'])->toBe(SettingSecretRedactor::REDACTED);
});

it('does not redact non-secret fields for integration settings', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->bigBlueButton()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $bbbSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'big_blue_button');
    expect($bbbSettings['value']['base_url'])->toBe('https://bbb.example.com')
        ->and($bbbSettings['value']['enabled'])->toBeFalse();
});

it('does not redact non-integration settings', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->contactInfo()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $contactSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'contact_info');
    expect($contactSettings)->not->toBeNull()
        ->and($contactSettings['value']['support_email'])->toBe('test@example.com');
});
