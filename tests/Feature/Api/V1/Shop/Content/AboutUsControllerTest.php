<?php

declare(strict_types=1);

use App\Data\Admin\MediaData;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\getJson;

it('returns about us data for shop (public)', function () {
    $response = getJson(route('api.v1.shop.aboutus.show'));
    $response->assertOk();

    $response->assertJsonStructure([
        'message',
        'data' => [
            'title',
            'main_block' => [
                'title', 'content', 'icon_url', 'subtitle'
            ],
            'images',
            'active_course_groups_block' => [
                'title', 'content', 'icon_url', 'subtitle'
            ],
            'capabilities_block' => [
                'title', 'content', 'icon_url', 'subtitle'
            ],
            'about_online_course_block_1' => [
                'title', 'content', 'icon_url', 'subtitle'
            ],
            'about_online_course_block_2' => [
                'title', 'content', 'icon_url', 'subtitle'
            ],
        ],
        'metadata',
    ]);
});
it('returns about us data with correct media', function () {
    Storage::fake('public');
    $image1 = \MediaUploader::fromSource(UploadedFile::fake()->image('image1.jpg'))
        ->toDisk('public')
        ->upload();
    $image2 = \MediaUploader::fromSource(UploadedFile::fake()->image('image2.jpg'))
        ->toDisk('public')
        ->upload();

    $aboutusData = [
        'title'                       => 'Test About Us',
        'main_block'                  => [
            'title'   => 'Main Block Title',
            'content' => 'Main Block Content',
            'icon'    => MediaData::from($image1),
        ],
        'images'                      => [MediaData::from($image1), MediaData::from($image2)],
        'active_course_groups_block'  => [
            'title'   => 'Active Course Groups Title',
            'content' => 'Active Course Groups Content',
            'icon'    => null,
        ],
        'capabilities_block'          => [
            'title'   => 'Capabilities Title',
            'content' => 'Capabilities Content',
            'icon'    => MediaData::from($image1),
        ],
        'about_online_course_block_1' => [
            'title'   => 'Online Course Block 1 Title',
            'content' => 'Online Course Block 1 Content',
            'icon'    => null,
        ],
        'about_online_course_block_2' => [
            'title'   => 'Online Course Block 2 Title',
            'content' => 'Online Course Block 2 Content',
            'icon'    => MediaData::from($image2),
        ],
    ];

    Setting::setValue(SettingKeyEnum::ABOUT_US, $aboutusData);

    $response = getJson(route('api.v1.shop.aboutus.show'));

    $response->assertOk();
    $responseData = $response->json('data');
    expect($responseData['title'])->toBe('Test About Us')
        ->and($responseData['main_block']['icon_url'])->toBe($image1->getUrl())
        ->and($responseData['images'])->toBe([$image1->getUrl(), $image2->getUrl()])
        ->and($responseData['capabilities_block']['icon_url'])->toBe($image1->getUrl())
        ->and($responseData['about_online_course_block_2']['icon_url'])->toBe($image2->getUrl());
});
