<?php

declare(strict_types=1);

uses(Tests\AuthTestTrait::class);

it('can get about us settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    $response = $this->getJson(route('api.v1.admin.settings.about-us.show'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'title',
                'main_block' => [
                    'title',
                    'content',
                    'icon',
                ],
                'images',
                'active_course_groups_block' => [
                    'title',
                    'content',
                    'icon',
                ],
                'capabilities_block' => [
                    'title',
                    'content',
                    'icon',
                ],
                'about_online_course_block_1' => [
                    'title',
                    'content',
                    'icon',
                ],
                'about_online_course_block_2' => [
                    'title',
                    'content',
                    'icon',
                ],
            ],
            'metadata',
        ]);
});

it('can update about us settings', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    $image1 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image1.jpg'))
        ->toDisk('public')
        ->upload();
    $image2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image1.jpg'))
        ->toDisk('public')
        ->upload();
    $icon1 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon1.jpg'))
        ->toDisk('public')
        ->upload();
    $aboutUsData = [
        'title'      => 'Updated About Us Title',
        'main_block' => [
            'title'   => 'Updated Main Block Title',
            'content' => 'Updated main block content with detailed information about the company.',
            'icon'    => $icon1->id,
        ],
        'images'                     => [$image1->id, $image2->id],
        'active_course_groups_block' => [
            'title'   => 'Updated Course Groups',
            'content' => '<ol><li>Updated Course Group 1</li><li>Updated Course Group 2</li></ol>',
            'icon'    => null,
        ],
        'capabilities_block' => [
            'title'   => 'Updated Capabilities',
            'content' => '<ul><li>Updated Capability 1</li><li>Updated Capability 2</li></ul>',
            'icon'    => null,
        ],
        'about_online_course_block_1' => [
            'title'   => 'Updated Online Course Block 1',
            'content' => 'Updated content about online courses and their benefits.',
            'icon'    => null,
        ],
        'about_online_course_block_2' => [
            'title'   => 'Updated Online Course Block 2',
            'content' => '<ul><li>Updated online course feature 1</li><li>Updated online course feature 2</li></ul>',
            'icon'    => null,
        ],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.about-us.update'), $aboutUsData);
    // array:3 [
    //  "message" => "About Us updated successfully."
    //  "data" => array:7 [
    //    "title" => "Updated About Us Title"
    //    "main_block" => array:4 [
    //      "title" => "Updated Main Block Title"
    //      "content" => "Updated main block content with detailed information about the company."
    //      "icon" => array:9 [
    //        "id" => 3
    //        "url" => "/storage/icon1.jpg"
    //        "size" => 695
    //        "file_name" => "icon1"
    //        "alt" => ""
    //        "mime_type" => "image/jpeg"
    //        "extension" => "jpg"
    //        "tag" => null
    //        "thumbnail" => null
    //      ]
    //      "subtitle" => null
    //    ]
    //    "images" => array:2 [
    //      0 => array:9 [
    //        "id" => 1
    //        "url" => "/storage/image1.jpg"
    //        "size" => 695
    //        "file_name" => "image1"
    //        "alt" => ""
    //        "mime_type" => "image/jpeg"
    //        "extension" => "jpg"
    //        "tag" => null
    //        "thumbnail" => null
    //      ]
    //      1 => array:9 [
    //        "id" => 2
    //        "url" => "/storage/image1-1.jpg"
    //        "size" => 695
    //        "file_name" => "image1-1"
    //        "alt" => ""
    //        "mime_type" => "image/jpeg"
    //        "extension" => "jpg"
    //        "tag" => null
    //        "thumbnail" => null
    //      ]
    //    ]
    //    "active_course_groups_block" => array:4 [
    //      "title" => "Updated Course Groups"
    //      "content" => "<ol><li>Updated Course Group 1</li><li>Updated Course Group 2</li></ol>"
    //      "icon" => null
    //      "subtitle" => null
    //    ]
    //    "capabilities_block" => array:4 [
    //      "title" => "Updated Capabilities"
    //      "content" => "<ul><li>Updated Capability 1</li><li>Updated Capability 2</li></ul>"
    //      "icon" => null
    //      "subtitle" => null
    //    ]
    //    "about_online_course_block_1" => array:4 [
    //      "title" => "Updated Online Course Block 1"
    //      "content" => "Updated content about online courses and their benefits."
    //      "icon" => null
    //      "subtitle" => null
    //    ]
    //    "about_online_course_block_2" => array:4 [
    //      "title" => "Updated Online Course Block 2"
    //      "content" => "<ul><li>Updated online course feature 1</li><li>Updated online course feature 2</li></ul>"
    //      "icon" => null
    //      "subtitle" => null
    //    ]
    //  ]
    //  "metadata" => []
    // ]
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                'title',
                'main_block' => [
                    'title',
                    'content',
                    'icon' => [
                        'id',
                        'url',
                        'size',
                        'file_name',
                        'alt',
                        'mime_type',
                        'extension',
                        'tag',
                        'thumbnail',
                    ],
                    'subtitle',
                ],
                'images' => [
                    '*' => [
                        'id',
                        'url',
                        'size',
                        'file_name',
                        'alt',
                        'mime_type',
                        'extension',
                        'tag',
                        'thumbnail',
                    ],
                ],
                'active_course_groups_block' => [
                    'title',
                    'content',
                    'icon',
                    'subtitle',
                ],
                'capabilities_block' => [
                    'title',
                    'content',
                    'icon',
                    'subtitle',
                ],
                'about_online_course_block_1' => [
                    'title',
                    'content',
                    'icon',
                    'subtitle',
                ],
                'about_online_course_block_2' => [
                    'title',
                    'content',
                    'icon',
                    'subtitle',
                ],
            ],
            'metadata',
        ]);

    // Verify the setting was updated
    $setting = App\Models\Setting::where('key', 'about_us')->first();
    expect($setting)->not->toBeNull()
        ->and($setting->value['title'])->toBe('Updated About Us Title')
        ->and($setting->value['main_block']['title'])->toBe('Updated Main Block Title')
        ->and($setting->value['capabilities_block']['content'])->toContain('Updated Capability 1');
});

it('validates about us data - missing required fields', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    $invalidData = [
        'title'      => '', // Empty title
        'main_block' => [
            'title'   => '', // Empty title
            'content' => '', // Empty content
            'icon'    => null,
        ],
        'images'                     => [],
        'active_course_groups_block' => [
            'title'   => '',
            'content' => '',
            'icon'    => null,
        ],
        'capabilities_block' => [
            'title'   => '',
            'content' => '',
            'icon'    => null,
        ],
        'about_online_course_block_1' => [
            'title'   => '',
            'content' => '',
            'icon'    => null,
        ],
        'about_online_course_block_2' => [
            'title'   => '',
            'content' => '',
            'icon'    => null,
        ],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.about-us.update'), $invalidData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'title',
            'main_block.title',
            'main_block.content',
            'active_course_groups_block.title',
            'active_course_groups_block.content',
            'capabilities_block.title',
            'capabilities_block.content',
            'about_online_course_block_1.title',
            'about_online_course_block_1.content',
            'about_online_course_block_2.title',
            'about_online_course_block_2.content',
        ]);
});

it('validates about us data - invalid data types', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    $invalidData = [
        'title'      => str_repeat('A', 300), // Too long title
        'main_block' => [
            'title'   => str_repeat('A', 300), // Too long title
            'content' => 'Valid content',
            'icon'    => 999, // non exitance image
        ],
        'images'                     => 'not-an-array', // Should be array
        'active_course_groups_block' => [
            'title'   => str_repeat('A', 300),
            'content' => 'Valid content',
            'icon'    => null,
        ],
        'capabilities_block' => [
            'title'   => str_repeat('A', 300),
            'content' => 'Valid content',
            'icon'    => null,
        ],
        'about_online_course_block_1' => [
            'title'   => str_repeat('A', 300),
            'content' => 'Valid content',
            'icon'    => null,
        ],
        'about_online_course_block_2' => [
            'title'   => str_repeat('A', 300),
            'content' => 'Valid content',
            'icon'    => null,
        ],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.about-us.update'), $invalidData);
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'title',
            'main_block.title',
            'main_block.icon',
            'images',
            'active_course_groups_block.title',
            'capabilities_block.title',
            'about_online_course_block_1.title',
            'about_online_course_block_2.title',
        ]);
});

it('cannot access about us settings without auth', function (): void {
    $this->unauthorized_user();
    $response = $this->getJson(route('api.v1.admin.settings.about-us.show'));
    $response->assertStatus(403);

    $aboutUsData = [
        'title'      => 'Test Title',
        'main_block' => [
            'title'   => 'Test Main Block',
            'content' => 'Test content',
            'icon'    => null,
        ],
        'images'                     => [],
        'active_course_groups_block' => [
            'title'   => 'Test Groups',
            'content' => 'Test content',
            'icon'    => null,
        ],
        'capabilities_block' => [
            'title'   => 'Test Capabilities',
            'content' => 'Test content',
            'icon'    => null,
        ],
        'about_online_course_block_1' => [
            'title'   => 'Test Online 1',
            'content' => 'Test content',
            'icon'    => null,
        ],
        'about_online_course_block_2' => [
            'title'   => 'Test Online 2',
            'content' => 'Test content',
            'icon'    => null,
        ],
    ];

    $response = $this->putJson(route('api.v1.admin.settings.about-us.update'), $aboutUsData);
    $response->assertStatus(403);
});

it('returns default values when no about us setting exists', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);

    // Ensure no about_us setting exists
    App\Models\Setting::where('key', 'about_us')->delete();

    $response = $this->getJson(route('api.v1.admin.settings.about-us.show'));
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['title'])->toBe('درباره جدویار')
        ->and($data['main_block']['title'])->toBe('جدویار، مرکز آموزش‌های تخصصی و مهارتی')
        ->and($data['active_course_groups_block']['title'])->toBe('گروه‌های آموزشی فعال')
        ->and($data['capabilities_block']['title'])->toBe('قابلیت‌های جدویار')
        ->and($data['about_online_course_block_1']['title'])->toBe('دوره‌های آنلاین جدویار')
        ->and($data['about_online_course_block_2']['title'])->toBe('چرا دوره‌های آنلاین جدویار؟');
});
