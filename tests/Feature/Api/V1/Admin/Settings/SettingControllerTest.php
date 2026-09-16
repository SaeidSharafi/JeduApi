<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);

use App\Enums\PermissionEnum;
use App\Http\Controllers\Api\Admin\Settings\SettingController;
use App\Models\Setting;
use App\Services\SettingSecretRedactor;

covers(SettingController::class);

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

it('redacts spot_player api_key in index response', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->spotPlayer()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $response->assertStatus(200);

    $spotSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'spot_player');
    expect($spotSettings)->not->toBeNull()
        ->and($spotSettings['value']['api_key'])->toBe(SettingSecretRedactor::REDACTED);
});

it('redacts skyroom api_key and secret in index response', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->create([
        'key'   => 'skyroom',
        'value' => [
            'enabled'  => false,
            'base_url' => 'https://www.skyroom.online/skyroom/api',
            'api_key'  => 'skyroom-plain-key',
            'secret'   => 'skyroom-plain-secret',
        ],
        'type'  => 'json',
        'group' => 'integrations',
    ]);

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $response->assertStatus(200);

    $skyroomSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'skyroom');
    expect($skyroomSettings)->not->toBeNull()
        ->and($skyroomSettings['value']['api_key'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($skyroomSettings['value']['secret'])->toBe(SettingSecretRedactor::REDACTED)
        ->and($skyroomSettings['value']['base_url'])->toBe('https://www.skyroom.online/skyroom/api');
});

it('does not redact non-secret fields for integration settings', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->spotPlayer()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $spotSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'spot_player');
    expect($spotSettings['value']['endpoint'])->toBe('https://panel.spotplayer.ir/license/edit/')
        ->and($spotSettings['value']['enabled'])->toBeFalse();
});

it('does not redact non-integration settings', function (): void {
    $this->authorized_user([PermissionEnum::SETTING_VIEW_ANY->value]);
    Setting::factory()->contactInfo()->create();

    $response = $this->getJson(route('api.v1.admin.settings.index'));

    $contactSettings = collect($response->json('data'))->flatten(1)->firstWhere('key', 'contact_info');
    expect($contactSettings)->not->toBeNull()
        ->and($contactSettings['value']['support_email'])->toBe('test@example.com');
});
