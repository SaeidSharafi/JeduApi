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
        ->assertJsonPath('data.home_h1', null)
        ->assertJsonMissingPath('data.favicon');
});

it('returns the saved title and favicon url', function (): void {
    Setting::factory()->create([
        'key'   => SettingKeyEnum::SITE_IDENTITY->value,
        'value' => [
            'site_title'  => 'جهاد دانشگاهی',
            'favicon'     => null,
            'favicon_url' => 'https://cdn.example.com/favicon.ico',
            'home_h1' => 'Learn with us',
            'home_description' => 'Explore our courses.',
            'home_meta_title' => 'Courses | Jedu',
            'home_meta_description' => 'Find your next course.',
        ],
    ]);

    $this->getJson('/api/v1/shop/site-identity')
        ->assertStatus(200)
        ->assertJsonPath('data.site_title', 'جهاد دانشگاهی')
        ->assertJsonPath('data.favicon_url', 'https://cdn.example.com/favicon.ico')
        ->assertJsonPath('data.home_h1', 'Learn with us')
        ->assertJsonPath('data.home_description', 'Explore our courses.')
        ->assertJsonPath('data.home_meta_title', 'Courses | Jedu')
        ->assertJsonPath('data.home_meta_description', 'Find your next course.');
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
