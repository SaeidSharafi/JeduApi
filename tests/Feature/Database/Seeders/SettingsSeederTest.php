<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use Database\Seeders\SettingsSeeder;

/*
| Mutation notes: `database/seeders` is outside phpunit's `<source>` set (app only),
| which is also the mutator source, so this file cannot declare a target:
| `covers(SettingsSeeder::class)` fails with "not a valid target for code coverage"
| and `mutates(SettingsSeeder::class)` selects 0 mutations for 0 files. The
| assertions below therefore pin every value the seeder decides — the enable flag,
| the resolved config path for the endpoint, and the group — so a regression still
| fails loudly.
*/

it('seeds the spot player row from the spotplayer endpoint config with the enable flag and integrations group', function (): void {
    config()->set('services.spotplayer.endpoint', 'https://spotplayer.seeded.test/license/edit/');

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
