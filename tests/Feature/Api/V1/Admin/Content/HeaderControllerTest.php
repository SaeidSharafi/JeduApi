<?php

declare(strict_types=1);

uses(Tests\AuthTestTrait::class);

it('can get header settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    $response = $this->getJson(route('api.v1.admin.settings.header.show'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [],
            'metadata',
        ]);
});

it('can update header settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');

    $logo = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('logo.jpg'))
        ->toDisk('public')
        ->upload();

    $headerData = [
        'logo'             => $logo->id,
        'navigation_links' => [
            ['title' => 'About Us', 'url' => '/about-us', 'order' => 0],
            ['title' => 'Contact Us', 'url' => '/contact-us', 'order' => 2],
            ['title' => 'Blog', 'url' => '/blog', 'order' => 1],
        ],
        'contact_phone' => '123-456-7890',
        'contact_email' => 'contactus@example.com',
    ];

    $response = $this->putJson(route('api.v1.admin.settings.header.update'), $headerData);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [],
            'metadata',
        ]);
    $response->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($logo): void {
        $json
            ->where('data.contact_phone', '123-456-7890')
            ->where('data.contact_email', 'contactus@example.com')
            ->where('data.navigation_links.0.title', 'About Us')
            ->where('data.navigation_links.1.title', 'Blog')
            ->where('data.navigation_links.2.title', 'Contact Us')
            ->where('data.logo.url', $logo->getUrl())
            ->etc();
    });

    $setting = App\Models\Setting::where('key', 'header')->first();

    expect($setting)->not->toBeNull()
        ->and($setting->value['contact_phone'])->toBe('123-456-7890')
        ->and($setting->value['contact_email'])->toBe('contactus@example.com')
        ->and($setting->value['navigation_links'][0]['title'])->toBe('About Us')
        ->and($setting->value['navigation_links'][1]['title'])->toBe('Blog')
        ->and($setting->value['navigation_links'][2]['title'])->toBe('Contact Us')
        ->and($setting->value['logo'])->toBe($logo->id)
        ->and($setting->value['logo_url'])->toBe($logo->getUrl());

});

it('validates header data - missing required fields', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);

    $invalidData = [
        // 'logo' is optional
        // 'navigation_links' is required
        // 'contact_phone' is required
        // 'contact_email' is required
    ];

    $response = $this->putJson(route('api.v1.admin.settings.header.update'), $invalidData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'navigation_links',
            'contact_phone',
            'contact_email',
        ]);

    $invalidNavData = [
        'navigation_links' => [
            ['title' => '', 'url' => '/about-us'], // title is required
            ['title' => 'Contact Us', 'url' => ''], // url is required
        ],
        'contact_phone' => '123-456-7890',
        'contact_email' => 'example@example.com',
    ];

    $response = $this->putJson(route('api.v1.admin.settings.header.update'), $invalidNavData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'navigation_links.0.title',
            'navigation_links.1.url',
        ]);
});

it('cannot access header settings without auth', function (): void {
    $this->unauthorized_user();
    $response = $this->getJson(route('api.v1.admin.settings.header.show'));
    $response->assertStatus(403);

    $headerData = [
        'navigation_links' => [
            ['title' => 'About Us', 'url' => '/about-us', 'order' => 0],
            ['title' => 'Contact Us', 'url' => '/contact-us', 'order' => 2],
            ['title' => 'Blog', 'url' => '/blog', 'order' => 1],
        ],
        'contact_phone' => '123-456-7890',
        'contact_email' => 'example@example.com',
    ];
    $response = $this->putJson(route('api.v1.admin.settings.header.update'), $headerData);
    $response->assertStatus(403);
});

it('returns default values when no header setting exists', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    App\Models\Setting::where('key', 'header')->delete();
    $response = $this->getJson(route('api.v1.admin.settings.header.show'));
    $response->assertStatus(200);
    $data     = $response->json('data');
    $defaults = App\Data\Admin\Settings\HeaderData::getDefaults();
    expect($data['contact_phone'])->toBe($defaults['contact_phone'])
        ->and($data['contact_email'])->toBe($defaults['contact_email'])
        ->and($data['navigation_links'])->toBe($defaults['navigation_links'])
        ->and($data['logo'])->toBe($defaults['logo']);
});
