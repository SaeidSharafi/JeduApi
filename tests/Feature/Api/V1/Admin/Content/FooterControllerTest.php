<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);

it('can get footer settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    $response = $this->getJson(route('api.v1.admin.settings.footer.show'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [],
            'metadata',
        ]);
});

it('can update footer settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');

    $logo = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('logo.jpg'))
        ->toDisk('public')
        ->upload();

    // Create valid categories
    $cat1 = App\Models\Category::factory()->create(['name' => 'Cat1']);
    $cat2 = App\Models\Category::factory()->create(['name' => 'Cat2']);

    $footerData = [
        'logo'                  => $logo->id,
        'caption'               => 'Your partner in modern education.',
        'support_link'          => '/contact-us',
        'support_email_address' => 'support@jedu.ir',
        'addresses'             => ['Address 1', 'Address 2'],
        'categories'            => [$cat1->id, $cat2->id],
        'main_links'            => [
            ['title' => 'About Us', 'link' => '/about-us'],
            ['title' => 'Blog', 'link' => '/blog'],
        ],
        'social_media_links' => [
            [
                'platform' => 'instagram',
                'link'     => 'https://instagram.com/jedushop',
            ],
            [
                'platform' => 'linkedin',
                'link'     => 'https://linkedin.com/company/jedushop',
            ],
        ],
        'certifications' => [
            [
                'name'  => 'Enamad',
                'image' => null,
                'html'  => "<img src='enamad.jpg' alt='Enamad' />",
            ],
            [
                'name'  => 'Samandehi',
                'image' => null,
                'html'  => "<img src='samandehi.jpg' alt='Samandehi' />",
            ],
        ],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.footer.update'), $footerData);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [],
            'metadata',
        ]);

    $setting = App\Models\Setting::where('key', 'footer')->first();
    expect($setting)->not->toBeNull()
        ->and($setting->value['caption'])->toBe('Your partner in modern education.')
        ->and($setting->value['logo'])->toBe($logo->id)
        ->and($setting->value['logo_url'])->toBe($logo->getUrl())
        ->and($setting->value['logo_alt'])->toBe($logo->alt);
});

it('validates footer data - missing required fields', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);

    $invalidData = [
        'caption'               => '',
        'support_link'          => '',
        'support_email_address' => '',
        'addresses'             => [],
        'categories'            => [],
        'main_links'            => [],
        'social_media_links'    => [],
        'certifications'        => [],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.footer.update'), $invalidData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'caption',

            'support_link',
            'support_email_address',
        ]);
});

it('validates footer data - invalid data types', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    $invalidData = [
        'logo'                  => 'not-an-integer',
        'caption'               => str_repeat('A', 300),
        'support_link'          => 123,
        'support_email_address' => 'not-an-email',
        'addresses'             => 'not-an-array',
        'categories'            => ['not-an-id'],
        'main_links'            => ['not-an-array'],
        'social_media_links'    => 'not-an-array',
        'certifications'        => 'not-an-array',
    ];
    $response = $this->putJson(route('api.v1.admin.settings.footer.update'), $invalidData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'logo',
            'caption',
            'support_link',
            'support_email_address',
        ]);
});

it('cannot access footer settings without auth', function (): void {
    $this->unauthorized_user();
    $response = $this->getJson(route('api.v1.admin.settings.footer.show'));
    $response->assertStatus(403);

    // Create valid category for required field
    $cat        = App\Models\Category::factory()->create(['name' => 'Cat']);
    $footerData = [
        'logo'                  => null,
        'caption'               => 'Test Caption',
        'support_link'          => '/contact-us',
        'support_email_address' => 'support@jedu.ir',
        'addresses'             => ['Address 1'],
        'categories'            => [$cat->id],
        'main_links'            => [
            ['title' => 'About Us', 'link' => '/about-us'],
        ],
        'social_media_links' => [
            [
                'platform' => 'instagram',
                'link'     => 'https://instagram.com/jedushop',
            ],
            [
                'platform' => 'linkedin',
                'link'     => 'https://linkedin.com/company/jedushop',
            ],
        ],
        'certifications' => [
            [
                'name'  => 'Enamad',
                'image' => null,
                'html'  => "<img src='enamad.jpg' alt='Enamad' />",
            ],
            [
                'name'  => 'Samandehi',
                'image' => null,
                'html'  => "<img src='samandehi.jpg' alt='Samandehi' />",
            ],
        ],
    ];
    $response = $this->putJson(route('api.v1.admin.settings.footer.update'), $footerData);
    $response->assertStatus(403);
});

it('returns default values when no footer setting exists', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    App\Models\Setting::where('key', 'footer')->delete();
    $response = $this->getJson(route('api.v1.admin.settings.footer.show'));
    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data['caption'])->toBe('شریک شما در آموزش مدرن')
        ->and($data['main_links'][0]['title'])->toBe('درباره ما');
});
