<?php

declare(strict_types=1);

use App\Models\Setting;

describe('FooterController', function (): void {

    it('retrieves footer settings successfully', function (): void {

        Setting::create([
            'key' => 'footer',
            'value' => [
                'logo' => 'logo-tech.svg',
                'logo_url' => 'logo-tech.svg',
                'logo_alt' => 'جهاددانشگاهی قزوین',
                'caption' => 'شریک شما در آموزش مدرن',
                'support_email_address' => 'support@jedu.ir',
                'addresses' => [
                    [
                        'name' => 'دفتر مرکزی',
                        'address' => 'تهران، خیابان آزادی، پلاک ۱۲۳',
                        'location_url' => 'https://maps.example.com/?q=35.6892,51.3890',
                        'phone' => '۰۲۱-۱۲۳۴۵۶۷۸'
                    ]
                ],
                'categories' => [1, 2, 3, 4],
                'social_media_links' => [
                    [
                        'platform' => 'instagram',
                        'link' => 'https://instagram.com/jedushop'
                    ],
                    [
                        'platform' => 'linkedin',
                        'link' => 'https://linkedin.com/company/jedushop'
                    ]
                ],
                'certifications' => [
                    [
                        'name' => 'اینماد',
                        'image' => 'favicon-tech.svg',
                        'html' => ''
                    ],
                    [
                        'name' => 'ساماندهی',
                        'image' => 'favicon-art.svg',
                        'html' => ''
                    ]
                ]
            ]
        ]);

        \App\Models\Category::factory()->count(4)
            ->sequence(
                ['name'=> 'Art', 'slug' => 'art'],
                ['name'=> 'Design', 'slug' => 'design'],
                ['name'=> 'Photography', 'slug' => 'photography'],
                ['name'=> 'Technology', 'slug' => 'technology'],
                ['name' => 'Fashion', 'slug' => 'fashion'],
            )->create();



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

        //assert Categories are loaded
        $response->assertJsonFragment([
            'categories' => [
                ['name'=> 'Art', 'slug' => 'art'],
                ['name'=> 'Design', 'slug' => 'design'],
                ['name'=> 'Photography', 'slug' => 'photography'],
                ['name'=> 'Technology', 'slug' => 'technology'],
            ]
        ]);
    });
});
