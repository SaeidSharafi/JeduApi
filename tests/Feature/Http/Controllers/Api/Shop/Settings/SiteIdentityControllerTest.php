<?php

declare(strict_types=1);

use App\Data\Admin\Settings\SiteIdentityData as AdminSiteIdentityData;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;

it('returns the default identity when nothing has been saved', function (): void {
    $this->getJson('/api/v1/shop/site-identity')
        ->assertStatus(200)
        ->assertJsonPath('data.site_title', AdminSiteIdentityData::getDefaults()['site_title'])
        ->assertJsonPath('data.favicon_url', null)
        ->assertJsonMissingPath('data.favicon');
});

it('returns the saved title and favicon url', function (): void {
    Setting::factory()->create([
        'key'   => SettingKeyEnum::SITE_IDENTITY->value,
        'value' => [
            'site_title'  => 'جهاد دانشگاهی',
            'favicon'     => null,
            'favicon_url' => 'https://cdn.example.com/favicon.ico',
        ],
    ]);

    $this->getJson('/api/v1/shop/site-identity')
        ->assertStatus(200)
        ->assertJsonPath('data.site_title', 'جهاد دانشگاهی')
        ->assertJsonPath('data.favicon_url', 'https://cdn.example.com/favicon.ico');
});

it('serves the same payload to the admin panel before login', function (): void {
    Setting::factory()->create([
        'key'   => SettingKeyEnum::SITE_IDENTITY->value,
        'value' => [
            'site_title'  => 'پنل جهاد',
            'favicon'     => null,
            'favicon_url' => 'https://cdn.example.com/icon.png',
        ],
    ]);

    $this->getJson('/api/v1/admin/site-identity')
        ->assertStatus(200)
        ->assertJsonPath('data.site_title', 'پنل جهاد')
        ->assertJsonPath('data.favicon_url', 'https://cdn.example.com/icon.png');
});
