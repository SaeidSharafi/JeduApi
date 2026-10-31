<?php

declare(strict_types=1);

use App\Enums\System\SettingKeyEnum;
use App\Models\Category;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

describe('FooterController', function (): void {

    it('retrieves footer settings successfully', function (): void {
        Category::factory()->count(4)
            ->sequence(
                ['name' => 'Art', 'slug' => 'art'],
                ['name' => 'Design', 'slug' => 'design'],
                ['name' => 'Photography', 'slug' => 'photography'],
                ['name' => 'Technology', 'slug' => 'technology'],
            )->create();
        app(SettingsService::class)->set(SettingKeyEnum::FOOTER,
            [
                'logo'                  => 'logo-text.webp',
                'logo_url'              => 'logo-text.webp',
                'logo_alt'              => 'جهاددانشگاهی قزوین',
                'caption'               => 'شریک شما در آموزش مدرن',
                'support_email_address' => 'support@jedu.ir',
                'addresses'             => [
                    [
                        'name'         => 'دفتر مرکزی',
                        'address'      => 'تهران، خیابان آزادی، پلاک ۱۲۳',
                        'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                        'phone'        => '۰۲۱-۱۲۳۴۵۶۷۸',
                    ],
                ],
                'categories'         => Category::query()->orderBy('name')->pluck('id')->toArray(),
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
                        'name'      => 'اینماد',
                        'image'     => null,
                        'image_url' => 'enamad.webp',
                        'html'      => '',
                    ],
                    [
                        'name'      => 'ساماندهی',
                        'image'     => null,
                        'image_url' => 'saman.webp',
                        'html'      => '',
                    ],
                ],
            ],
        );

        Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion']);

        $response = $this->getJson(route('api.v1.shop.footer.index'));

        // Assert that the response status is 200 OK
        $response->assertStatus(200);

        // Assert that the response structure matches the expected format
        $response->assertJsonStructure([
            'data' => [
                'logo_url',
                'logo_alt',
                'caption',
                'support_email_address',
                'addresses',
                'categories',
                'social_media_links',
                'certifications',
            ],
            'message',
        ]);

        $response->assertJsonFragment([
            'categories' => [
                ['name' => 'Art', 'slug' => 'art'],
                ['name' => 'Design', 'slug' => 'design'],
                ['name' => 'Photography', 'slug' => 'photography'],
                ['name' => 'Technology', 'slug' => 'technology'],
            ],
        ]);
    });

    it('serves the stored certification image url instead of the media id', function (): void {
        Storage::fake('public');

        $badge = MediaUploader::fromSource(UploadedFile::fake()->image('enamad.png'))
            ->toDisk('public')
            ->upload();

        app(SettingsService::class)->set(SettingKeyEnum::FOOTER, [
            'logo'                  => null,
            'logo_url'              => null,
            'logo_alt'              => null,
            'caption'               => 'شریک شما در آموزش مدرن',
            'support_email_address' => 'support@jedu.ir',
            'addresses'             => [],
            'categories'            => [],
            'social_media_links'    => [],
            'certifications'        => [
                [
                    'name'      => 'اینماد',
                    'image'     => $badge->id,
                    'image_url' => $badge->getUrl(),
                    'html'      => null,
                ],
                [
                    'name'      => 'ساماندهی',
                    'image'     => null,
                    'image_url' => 'saman.webp',
                    'html'      => '<b>ساماندهی</b>',
                ],
            ],
        ]);

        $response = $this->getJson(route('api.v1.shop.footer.index'));

        $response->assertStatus(200)
            ->assertJsonPath('data.certifications.0.image', $badge->getUrl())
            ->assertJsonPath('data.certifications.1.image', 'saman.webp')
            ->assertJsonPath('data.certifications.1.html', '<b>ساماندهی</b>');
    });
});
