<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);

it('can get contact info settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    $response = $this->getJson(route('api.v1.admin.settings.contact-info.show'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'addresses',
                'working_hours',
                'support_email',
                'social_media_links',
            ],
            'metadata',
        ]);
});

it('can update contact info settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    $contactData = [
        'title'     => 'تماس با ما',
        'subtitle'  => 'خط اول<br><strong>خط دوم</strong><script>alert(1)</script>',
        'addresses' => [
            [
                'name'         => 'Updated Office',
                'address'      => 'Updated Address 123',
                'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                'phone'        => '021-12345678',
            ],
        ],
        'working_hours'      => 'Saturday to Wednesday, 9am to 5pm',
        'support_email'      => 'updated@jedu.ir',
        'social_media_links' => [
            [
                'platform' => 'instagram',
                'link'     => 'https://instagram.com/jedushop',
            ],
        ],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.contact-info.update'), $contactData);
    $response->assertStatus(200)
        ->assertJsonPath('data.title', 'تماس با ما')
        ->assertJsonPath('data.subtitle', 'خط اول<br /><strong>خط دوم</strong>')
        ->assertJsonStructure([
            'message',
            'data',
            'metadata',
        ]);

    // Verify the setting was updated
    $setting = App\Models\Setting::where('key', 'contact_info')->first();
    expect($setting)->not->toBeNull();
    expect($setting->value['addresses'][0]['name'])->toBe('Updated Office');
    expect($setting->value['title'])->toBe('تماس با ما');
    expect($setting->value['subtitle'])->toBe('خط اول<br /><strong>خط دوم</strong>');
    $this->getJson(route('api.v1.shop.contactpage.show'))
        ->assertOk()
        ->assertJsonPath('data.title', 'تماس با ما')
        ->assertJsonPath('data.subtitle', 'خط اول<br /><strong>خط دوم</strong>');
});

it('validates contact info data', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    $invalidData = [
        'addresses'          => [], // Empty addresses
        'working_hours'      => '',
        'support_email'      => 'invalid-email',
        'social_media_links' => [],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.contact-info.update'), $invalidData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['addresses', 'working_hours', 'support_email', 'social_media_links']);
});

it('returns defaults for title and subtitle in older saved contact settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    $settings = App\Data\Admin\Settings\ContactInfoData::getDefaults();
    unset($settings['title'], $settings['subtitle']);
    app(App\Services\SettingsService::class)->set(App\Enums\System\SettingKeyEnum::CONTACT_INFO, $settings, 'json', 'cms');

    $this->getJson(route('api.v1.admin.settings.contact-info.show'))
        ->assertOk()
        ->assertJsonPath('data.title', '')
        ->assertJsonPath('data.subtitle', 'برای مشاوره دوره‌ها، پیگیری سفارش یا پرسش‌های آموزشی با ما در تماس باشید.');
    $this->getJson(route('api.v1.shop.contactpage.show'))
        ->assertOk()
        ->assertJsonPath('data.title', '')
        ->assertJsonPath('data.subtitle', 'برای مشاوره دوره‌ها، پیگیری سفارش یا پرسش‌های آموزشی با ما در تماس باشید.');
});

it('cannot access settings without auth', function (): void {
    $this->unauthorized_user();
    $response = $this->getJson(route('api.v1.admin.settings.index'));
    $response->assertStatus(403);

    $response = $this->getJson(route('api.v1.admin.settings.contact-info.show'));
    $response->assertStatus(403);

    $response = $this->putJson(route('api.v1.admin.settings.contact-info.update'), [
        'addresses' => [
            [
                'name'         => 'Test Office',
                'address'      => 'Test Address 123',
                'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                'phone'        => '021-12345678',
            ],
        ],
        'working_hours'      => 'Test working hours',
        'support_email'      => 'test@jedu.ir',
        'social_media_links' => [
            [
                'platform' => 'instagram',
                'link'     => 'https://instagram.com/test',
            ],
        ],
    ]);
    $response->assertStatus(403);
});
