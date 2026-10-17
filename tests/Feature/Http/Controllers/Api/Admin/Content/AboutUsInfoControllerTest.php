<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);

mutates(App\Data\ArticleSectionCreateData::class);

beforeEach(function (): void {
    // A compact-level CKEditor / Word paste: a heading to downgrade, an event
    // handler, pasted typography and colour, a highlight, a scheme-less link, a
    // list, an executable subtree, an HTML-embed subtree and an image (which
    // the compact level does not allow, so it disappears).
    $this->dirtyContent = '<h1 onclick="alert(1)">عنوان</h1>'
        .'<p class="lead" style="font-family: Arial; color: red; text-align: center">متن <mark>مهم</mark> '
        .'<a href="javascript:alert(1)" target="_blank">لینک</a></p>'
        .'<ul><li>یک</li><li>دو</li></ul>'
        .'<script>alert(1)</script>'
        .'<div class="raw-html-embed"><p>arbitrary</p></div>'
        .'<img src="https://example.test/pic.png" alt="pic">';

    $this->cleanContent = '<h2>عنوان</h2>'
        .'<p style="text-align: center">متن <mark>مهم</mark> '
        .'<a target="_blank" rel="noopener noreferrer">لینک</a></p>'
        .'<ul><li>یک</li><li>دو</li></ul>';
});

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

it('stores sanitized content for all five about us blocks and leaves the rest untouched', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value]);
    Storage::fake('public');
    $icon = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon.jpg'))
        ->toDisk('public')
        ->upload();
    $image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();

    $payload = aboutUsSettingsPayload($this->dirtyContent, $icon->id, [$image->id]);

    $response = $this->putJson(route('api.v1.admin.settings.about-us.update'), $payload);
    $response->assertOk();

    $stored = App\Models\Setting::where('key', 'about_us')->firstOrFail()->value;

    foreach (aboutUsBlockNames() as $blockName) {
        expect($stored[$blockName]['content'])->toBe($this->cleanContent)
            ->and($response->json("data.{$blockName}.content"))->toBe($this->cleanContent)
            ->and($stored[$blockName]['title'])->toBe('بلوک <b>عنوان</b>')
            ->and($stored[$blockName]['subtitle'])->toBe('<i>زیرنویس</i>');
    }

    expect($stored['title'])->toBe('درباره ما <b>عنوان</b>')
        ->and($stored['main_block']['icon'])->toBe($icon->id)
        ->and($stored['active_course_groups_block']['icon'])->toBeNull()
        ->and($stored['images'])->toBe([$image->id]);
});

it('round-trips the sanitized about us payload byte-identically through the read and write endpoints', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::SETTING_UPDATE->value, App\Enums\PermissionEnum::SETTING_VIEW_ANY->value]);
    Storage::fake('public');
    $icon = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon.jpg'))
        ->toDisk('public')
        ->upload();

    $this->putJson(
        route('api.v1.admin.settings.about-us.update'),
        aboutUsSettingsPayload($this->dirtyContent, $icon->id)
    )->assertOk();

    $stored = App\Models\Setting::where('key', 'about_us')->firstOrFail()->value;
    expect($stored['main_block']['content'])->toBe($this->cleanContent);

    $served = $this->getJson(route('api.v1.admin.settings.about-us.show'))->json('data');

    $roundTrip = [
        'title'  => $served['title'],
        'images' => array_map(static fn (array $servedImage): int => $servedImage['id'], $served['images']),
    ];

    foreach (aboutUsBlockNames() as $blockName) {
        $roundTrip[$blockName] = [
            'title'    => $served[$blockName]['title'],
            'content'  => $served[$blockName]['content'],
            'icon'     => $served[$blockName]['icon']['id'] ?? null,
            'subtitle' => $served[$blockName]['subtitle'],
        ];
    }

    $this->putJson(route('api.v1.admin.settings.about-us.update'), $roundTrip)->assertOk();

    expect(App\Models\Setting::where('key', 'about_us')->firstOrFail()->value)->toBe($stored);
});

/**
 * @return list<string>
 */
function aboutUsBlockNames(): array
{
    return [
        'main_block',
        'active_course_groups_block',
        'capabilities_block',
        'about_online_course_block_1',
        'about_online_course_block_2',
    ];
}

/**
 * @return array<string, mixed>
 */
function aboutUsSettingsPayload(string $content, ?int $iconId = null, array $imageIds = []): array
{
    $block = static fn (string $blockContent, ?int $blockIcon = null): array => [
        'title'    => 'بلوک <b>عنوان</b>',
        'content'  => $blockContent,
        'icon'     => $blockIcon,
        'subtitle' => '<i>زیرنویس</i>',
    ];

    return [
        'title'                       => 'درباره ما <b>عنوان</b>',
        'main_block'                  => $block($content, $iconId),
        'images'                      => $imageIds,
        'active_course_groups_block'  => $block($content),
        'capabilities_block'          => $block($content),
        'about_online_course_block_1' => $block($content),
        'about_online_course_block_2' => $block($content),
    ];
}
