<?php

declare(strict_types=1);

uses(Tests\AuthTestTrait::class);

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
            'metadata'
        ]);
});

it('can update contact info settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    $contactData = [
        'addresses' => [
            [
                'name' => 'Updated Office',
                'address' => 'Updated Address 123',
                'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                'phone' => '021-12345678',
            ],
        ],
        'working_hours' => 'Saturday to Wednesday, 9am to 5pm',
        'support_email' => 'updated@jedu.ir',
        'social_media_links' => [
            [
                'platform' => 'instagram',
                'link' => 'https://instagram.com/jedushop',
            ],
        ],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.contact-info.update'), $contactData);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data',
            'metadata'
        ]);

    // Verify the setting was updated
    $setting = App\Models\Setting::where('key', 'contact_info')->first();
    expect($setting)->not->toBeNull();
    expect($setting->value['addresses'][0]['name'])->toBe('Updated Office');
});

it('validates contact info data', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    $invalidData = [
        'addresses' => [], // Empty addresses
        'working_hours' => '',
        'support_email' => 'invalid-email',
        'social_media_links' => [],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.contact-info.update'), $invalidData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['addresses', 'working_hours', 'support_email', 'social_media_links']);
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
                'name' => 'Test Office',
                'address' => 'Test Address 123',
                'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                'phone' => '021-12345678',
            ],
        ],
        'working_hours' => 'Test working hours',
        'support_email' => 'test@jedu.ir',
        'social_media_links' => [
            [
                'platform' => 'instagram',
                'link' => 'https://instagram.com/test',
            ],
        ],
    ]);
    $response->assertStatus(403);
});
