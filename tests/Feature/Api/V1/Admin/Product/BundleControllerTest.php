<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\System\MorphTypeEnum;
use App\Models\Bundle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseHas;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    UploadedFile::fake();
    Storage::fake('public');

    $this->cover = MediaUploader::fromSource(UploadedFile::fake()->image('bundle-cover.jpg'))
        ->toDisk('public')
        ->upload();
    $this->gallery = MediaUploader::fromSource(UploadedFile::fake()->image('bundle-gallery.jpg'))
        ->toDisk('public')
        ->upload();
});

function bundleAdminPayload(array $overrides = []): array
{
    return [
        'full_name'        => 'Full Stack Package',
        'slug'             => 'full-stack-package',
        'description'      => 'A complete bundle for full stack development.',
        'status'           => 'published',
        'short_name'       => 'FULLSTACK',
        'meta_title'       => 'Full Stack Package Online Course Bundle',
        'meta_description' => 'A complete full stack development bundle with practical courses and guided learning for developers.',
        'meta_keywords'    => 'full stack, bundle, development',
        'properties'       => ['duration' => '3 months'],
        'additional_info'  => ['includes' => '12 courses'],
        'faq'              => [['question' => 'Refunds?', 'answer' => 'Within 7 days']],
        'media'            => [
            'cover'   => [],
            'gallery' => [],
            'video'   => [],
        ],
        ...$overrides,
    ];
}

it('can create a bundle with media', function (): void {
    $this->authorized_user([PermissionEnum::PRODUCT_CREATE->value]);

    $response = $this->postJson(route('api.v1.admin.bundles.store'), bundleAdminPayload([
        'media' => [
            'cover'   => [$this->cover->id],
            'gallery' => [$this->gallery->id],
            'video'   => [],
        ],
    ]));

    $response->assertCreated();

    $bundle = Bundle::query()->where('slug', 'full-stack-package')->firstOrFail();

    assertDatabaseHas('bundles', [
        'id'               => $bundle->id,
        'full_name'        => 'Full Stack Package',
        'status'           => 'published',
        'thumbnail_url'    => $this->cover->getUrl(),
        'meta_title'       => 'Full Stack Package Online Course Bundle',
        'meta_description' => 'A complete full stack development bundle with practical courses and guided learning for developers.',
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $this->cover->id,
        'mediable_id'   => $bundle->id,
        'mediable_type' => MorphTypeEnum::BUNDLE->value,
        'tag'           => 'cover',
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $this->gallery->id,
        'mediable_id'   => $bundle->id,
        'mediable_type' => MorphTypeEnum::BUNDLE->value,
        'tag'           => 'gallery',
    ]);
});

it('rejects an invalid bundle create request', function (): void {
    $this->authorized_user([PermissionEnum::PRODUCT_CREATE->value]);

    $this->postJson(route('api.v1.admin.bundles.store'), [
        'full_name'   => null,
        'slug'        => 'invalid slug',
        'description' => null,
        'status'      => null,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name', 'slug', 'description', 'status', 'media']);
});

it('can update a bundle and synchronize its media', function (): void {
    $bundle = Bundle::factory()->create();
    $bundle->attachMedia($this->gallery, 'gallery');
    $this->authorized_user([PermissionEnum::PRODUCT_UPDATE->value]);

    $response = $this->putJson(route('api.v1.admin.bundles.update', $bundle), bundleAdminPayload([
        'slug'      => $bundle->slug,
        'full_name' => 'Updated Bundle',
        'media'     => [
            'cover'   => [$this->cover->id],
            'gallery' => [],
            'video'   => [],
        ],
    ]));

    $response->assertOk();

    assertDatabaseHas('bundles', [
        'id'            => $bundle->id,
        'full_name'     => 'Updated Bundle',
        'thumbnail_url' => $this->cover->getUrl(),
        'meta_title'    => 'Full Stack Package Online Course Bundle',
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $this->cover->id,
        'mediable_id'   => $bundle->id,
        'mediable_type' => MorphTypeEnum::BUNDLE->value,
        'tag'           => 'cover',
    ]);
    $this->assertDatabaseMissing('mediables', [
        'media_id'      => $this->gallery->id,
        'mediable_id'   => $bundle->id,
        'mediable_type' => MorphTypeEnum::BUNDLE->value,
        'tag'           => 'gallery',
    ]);
});

it('returns bundle media from the admin show endpoint', function (): void {
    $bundle = Bundle::factory()->create();
    $bundle->attachMedia($this->cover, 'cover');
    $this->authorized_user([PermissionEnum::PRODUCT_VIEW->value]);

    $this->getJson(route('api.v1.admin.bundles.show', $bundle))
        ->assertOk()
        ->assertJsonPath('data.id', $bundle->id)
        ->assertJsonPath('data.media.cover.0.id', $this->cover->id)
        ->assertJsonPath('data.media.cover.0.tag', 'cover');
});
