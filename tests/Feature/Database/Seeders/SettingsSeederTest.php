<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;

it('seeds the spot player row from the spotplayer endpoint config with the enable flag and integrations group', function (): void {
    config()->set('provisioning.providers.spotplayer.endpoint', 'https://spotplayer.seeded.test/license/edit/');

    $this->seed(SettingsSeeder::class);

    $setting = Setting::query()->where('key', SettingKeyEnum::SPOT_PLAYER->value)->first();

    expect($setting)->not->toBeNull()
        ->and($setting->group)->toBe('integrations')
        ->and($setting->type)->toBe('json')
        ->and($setting->value)->toMatchArray([
            'enabled'  => false,
            'endpoint' => 'https://spotplayer.seeded.test/license/edit/',
        ]);
});

it('seeds the shipped default spot player endpoint', function (): void {
    $this->seed(SettingsSeeder::class);

    $setting = Setting::query()->where('key', SettingKeyEnum::SPOT_PLAYER->value)->first();

    expect($setting->value['endpoint'])->toBe('https://panel.spotplayer.ir/license/edit/');
});
