<?php

declare(strict_types=1);

use App\Enums\MorphTypeEnum;
use App\Models\DigitalAsset;

uses(Tests\AuthTestTrait::class);
beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
    Storage::fake('local');
    $this->cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $this->gallery = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('gallery.jpg'))
        ->toDisk('public')
        ->upload();
    $this->video = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()
        ->create('video.mp4', 1000, 'video/mp4'))
        ->toDisk('public')
        ->upload();
});
describe('list filters', function (): void {
    it('can filter by name', function () {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        DigitalAsset::factory()->count(10)->create();
        $digitalAsset = DigitalAsset::factory()->create(['name' => 'Test Asset']);
        $response     = $this->getJson(route('api.v1.admin.digital-asset.index', ['filter[name]' => $digitalAsset->name]));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => $digitalAsset->name]);
    });

    it('can filter by slug', function () {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        DigitalAsset::factory()->count(10)->create();
        $digitalAsset = DigitalAsset::factory()->create(['slug' => 'test-asset']);
        $response     = $this->getJson(route('api.v1.admin.digital-asset.index', ['filter[slug]' => $digitalAsset->slug]));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['slug' => $digitalAsset->slug]);
    });
    it('can filter by status', function () {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        DigitalAsset::factory()->count(10)->create();
        $digitalAsset = DigitalAsset::factory()->create(['status' => \App\Enums\PublicationStatusEnum::DRAFT]);
        $response     = $this->getJson(route('api.v1.admin.digital-asset.index', ['filter[status]' => $digitalAsset->status->value]));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['slug' => $digitalAsset->slug]);
    });
    it('can filter by is_attachable_to_course', function () {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        DigitalAsset::factory()->count(10)
            ->nonAttachable()
            ->create();
        $digitalAsset = DigitalAsset::factory()->create(['is_attachable_to_course' => true]);
        $response     = $this->getJson(route('api.v1.admin.digital-asset.index', ['filter[is_attachable_to_course]' => 'true']));
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['slug' => $digitalAsset->slug]);
    });

    it('can sort by name', function () {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        DigitalAsset::factory()->count(5)->create();
        $response      = $this->getJson(route('api.v1.admin.digital-asset.index', ['sort' => 'name']));
        $digitalAssets = DigitalAsset::orderBy('name')->get();
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.data.0.slug', $digitalAssets[0]->slug)
            ->assertJsonPath('data.data.1.slug', $digitalAssets[1]->slug)
            ->assertJsonPath('data.data.2.slug', $digitalAssets[2]->slug)
            ->assertJsonPath('data.data.3.slug', $digitalAssets[3]->slug)
            ->assertJsonPath('data.data.4.slug', $digitalAssets[4]->slug);
    });
    it('can sort by slug', function () {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        DigitalAsset::factory()->count(5)->create();
        $response = $this->getJson(route('api.v1.admin.digital-asset.index', ['sort' => 'slug']));

        $digitalAssets = DigitalAsset::orderBy('slug')->get();
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.data.0.slug', $digitalAssets[0]->slug)
            ->assertJsonPath('data.data.1.slug', $digitalAssets[1]->slug)
            ->assertJsonPath('data.data.2.slug', $digitalAssets[2]->slug)
            ->assertJsonPath('data.data.3.slug', $digitalAssets[3]->slug)
            ->assertJsonPath('data.data.4.slug', $digitalAssets[4]->slug);
    });

    it('can sort by status', function () {
        $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
        DigitalAsset::factory()->count(5)
            ->create();
        $response      = $this->getJson(route('api.v1.admin.digital-asset.index', ['sort' => 'status']));
        $digitalAssets = DigitalAsset::orderBy('status')->get();
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.data.0.slug', $digitalAssets[0]->slug)
            ->assertJsonPath('data.data.1.slug', $digitalAssets[1]->slug)
            ->assertJsonPath('data.data.2.slug', $digitalAssets[2]->slug)
            ->assertJsonPath('data.data.3.slug', $digitalAssets[3]->slug)
            ->assertJsonPath('data.data.4.slug', $digitalAssets[4]->slug);
    });

});
it('can get list of digital assets', function () {
    $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW_ANY->value]);
    DigitalAsset::factory()->count(10)->create();
    $response = $this->getJson(route('api.v1.admin.digital-asset.index'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'status',
                        'is_attachable_to_course',
                        'version',
                        'published_at',
                        'created_by',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
});

it('can get single digital asset', function () {
    $this->authorized_user([App\Enums\PermissionEnum::FILE_VIEW->value]);
    $digitalAsset = DigitalAsset::factory()->create();
    $preview      = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('preview.pdf'))
        ->toDisk('local')
        ->upload();
    $main = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('file.pdf'))
        ->toDisk('local')
        ->upload();

    $digitalAsset->attachMedia($preview, 'preview');
    $digitalAsset->attachMedia($main, 'main');
    $digitalAsset->attachMedia($this->cover, 'cover');
    $digitalAsset->attachMedia($this->gallery, 'gallery');
    $digitalAsset->attachMedia($this->video, 'video');
    $response = $this->getJson(route('api.v1.admin.digital-asset.show', $digitalAsset));
    $response->assertStatus(200)
        ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($main, $preview, $digitalAsset) {
            $json->where('data.id', $digitalAsset->id)
                ->where('data.name', $digitalAsset->name)
                ->where('data.slug', $digitalAsset->slug)
                ->where('data.description', $digitalAsset->description)
                ->where('data.version', $digitalAsset->version)
                ->where('data.page_count', $digitalAsset->page_count)
                ->where('data.duration_seconds', $digitalAsset->duration_seconds)
                ->where('data.is_attachable_to_course', $digitalAsset->is_attachable_to_course)
                ->where('data.status', [
                    'value' => $digitalAsset->status->value,
                    'label' => $digitalAsset->status->translate(),
                ])
                ->where('data.keywords', $digitalAsset->keywords)
                ->where('data.meta_title', $digitalAsset->meta_title)
                ->where('data.meta_description', $digitalAsset->meta_description)
                ->where('data.meta_keywords', $digitalAsset->meta_keywords)
                ->where('data.published_at', $this->toJalalitString($digitalAsset->published_at))
                ->where('data.created_by', $digitalAsset->created_by)
                ->where('data.created_at', $this->toJalalitString($digitalAsset->created_at))
                ->where('data.updated_at', $this->toJalalitString($digitalAsset->updated_at))
                ->where('data.attachments.preview.0.id', $preview->id)
                ->where('data.attachments.main.0.id', $main->id)
                ->where('data.media.cover.0.id', $this->cover->id)
                ->where('data.media.gallery.0.id', $this->gallery->id)
                ->where('data.media.video.0.id', $this->video->id)
                ->etc();
        });
});

it('can create digital asset', function () {
    $this->authorized_user([App\Enums\PermissionEnum::FILE_CREATE->value]);
    $digitalAsset = DigitalAsset::factory()
        ->make();
    Storage::fake('local');
    $preview = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('preview.pdf'))
        ->toDisk('local')
        ->upload();
    $main = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('file.pdf'))
        ->toDisk('local')
        ->upload();

    $categories = App\Models\Category::factory()->count(2)->create();
    $response   = $this->postJson(route('api.v1.admin.digital-asset.store'), [
        'name'                    => $digitalAsset->name,
        'slug'                    => $digitalAsset->slug,
        'description'             => $digitalAsset->description,
        'version'                 => $digitalAsset->version,
        'page_count'              => $digitalAsset->page_count,
        'duration_seconds'        => $digitalAsset->duration_seconds,
        'is_attachable_to_course' => $digitalAsset->is_attachable_to_course,
        'status'                  => $digitalAsset->status,
        'keywords'                => $digitalAsset->keywords,
        'meta_title'              => $digitalAsset->meta_title,
        'meta_description'        => $digitalAsset->meta_description,
        'meta_keywords'           => $digitalAsset->meta_keywords,
        'published_at'            => $this->toJalalitString($digitalAsset->published_at),
        'categories'              => $categories->pluck('id')->toArray(),
        'attachments'             => [
            'preview' => $preview->id,
            'main'    => $main->id,
        ],
        'media' => [
            'gallery' => [$this->gallery->id],
            'cover'   => [$this->cover->id],
            'video'   => [$this->video->id],
        ],
    ]);
    $response->assertStatus(201);

    $this->assertDatabaseHas('digital_assets', [
        'name'                    => $digitalAsset->name,
        'slug'                    => $digitalAsset->slug,
        'description'             => $digitalAsset->description,
        'version'                 => $digitalAsset->version,
        'page_count'              => $digitalAsset->page_count,
        'duration_seconds'        => $digitalAsset->duration_seconds,
        'is_attachable_to_course' => $digitalAsset->is_attachable_to_course,
        'status'                  => $digitalAsset->status,
        'keywords'                => $digitalAsset->keywords,
        'meta_title'              => $digitalAsset->meta_title,
        'meta_description'        => $digitalAsset->meta_description,
        'meta_keywords'           => $digitalAsset->meta_keywords,
        'published_at'            => $this->formatDate($digitalAsset->published_at),

    ])->assertCount(1, DigitalAsset::all());

    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => DigitalAsset::latest()->first()->id,
        'media_id'      => $preview->id,
    ]);
    $categories = $categories->all();
    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => DigitalAsset::latest()->first()->id,
        'media_id'      => $main->id,
    ]);

    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => DigitalAsset::latest()->first()->id,
        'media_id'      => $this->cover->id,
    ]);

    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => DigitalAsset::latest()->first()->id,
        'media_id'      => $this->gallery->id,
    ]);
    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => DigitalAsset::latest()->first()->id,
        'media_id'      => $this->video->id,
    ]);

    $this->assertDatabaseHas('categorizables', [
        'categorizable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'categorizable_id'   => DigitalAsset::latest()->first()->id,
        'category_id'        => $categories[0]->id,
    ]);

});

it('can update digital asset', function () {
    $this->authorized_user([App\Enums\PermissionEnum::FILE_UPDATE->value]);
    $digitalAsset = DigitalAsset::factory()->create()->fresh();
    $preview      = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('preview.pdf'))
        ->toDisk('local')
        ->upload();
    $main = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('file.pdf'))
        ->toDisk('local')
        ->upload();
    $digitalAsset->attachMedia($preview, 'preview');
    $digitalAsset->attachMedia($main, 'main');
    $digitalAssetUpdate = DigitalAsset::factory()
        ->make();
    $updatedData   = $digitalAssetUpdate->toArray();
    $previewUpdate = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('preview_updated.pdf'))
        ->toDisk('local')
        ->upload();
    $mainUpdate = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('file_updated.pdf'))
        ->toDisk('local')
        ->upload();
    $updatedData['attachments'] = [
        'preview' => $previewUpdate->id,
        'main'    => $mainUpdate->id,
    ];
    $updatedData['media'] = [
        'gallery' => [$this->gallery->id],
        'cover'   => [$this->cover->id],
        'video'   => [$this->video->id],
    ];
    $categories                  = App\Models\Category::factory()->count(2)->create();
    $updatedData['categories']   = $categories->pluck('id')->toArray();
    $updatedData['published_at'] = $this->toJalalitString($digitalAssetUpdate->published_at->format('Y-m-d H:i:s'));
    $response                    = $this->putJson(route('api.v1.admin.digital-asset.update', $digitalAsset), $updatedData);
    $response->assertStatus(200)
        ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($updatedData) {
            $json->where('data.name', $updatedData['name'])
                ->where('data.slug', $updatedData['slug'])
                ->where('data.description', $updatedData['description'])
                ->where('data.version', $updatedData['version'])
                ->where('data.page_count', $updatedData['page_count'])
                ->where('data.duration_seconds', $updatedData['duration_seconds'])
                ->where('data.is_attachable_to_course', $updatedData['is_attachable_to_course'])
                ->where('data.status', [
                    'value' => $updatedData['status'],
                    'label' => App\Enums\PublicationStatusEnum::from($updatedData['status'])->translate(),
                ])
                ->where('data.keywords', $updatedData['keywords'])
                ->where('data.meta_title', $updatedData['meta_title'])
                ->where('data.meta_description', $updatedData['meta_description'])
                ->where('data.meta_keywords', $updatedData['meta_keywords'])
                ->where('data.published_at', $updatedData['published_at'] ?? null)
                ->has('data.categories', 2)
                ->where('data.attachments.preview.0.id', $updatedData['attachments']['preview'])
                ->where('data.attachments.main.0.id', $updatedData['attachments']['main'])
                ->where('data.media.gallery.0.id', $this->gallery->id)
                ->where('data.media.cover.0.id', $this->cover->id)
                ->where('data.media.video.0.id', $this->video->id)
                ->etc();
        });

    $this->assertDatabaseHas('digital_assets', [
        'id'                      => $digitalAsset->id,
        'name'                    => $updatedData['name'],
        'slug'                    => $updatedData['slug'],
        'description'             => $updatedData['description'],
        'version'                 => $updatedData['version'],
        'page_count'              => $updatedData['page_count'],
        'duration_seconds'        => $updatedData['duration_seconds'],
        'is_attachable_to_course' => $updatedData['is_attachable_to_course'],
        'status'                  => App\Enums\PublicationStatusEnum::from($updatedData['status'])->value,
        'keywords'                => $updatedData['keywords'],
        'meta_title'              => $updatedData['meta_title'],
        'meta_description'        => $updatedData['meta_description'],
        'meta_keywords'           => $updatedData['meta_keywords'],
        'published_at'            => $this->formatDate($digitalAssetUpdate->published_at),
    ])->assertCount(1, DigitalAsset::all());

    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $previewUpdate->id,
    ]);
    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $mainUpdate->id,
    ]);

    $this->assertDatabaseHas('categorizables', [
        'categorizable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'categorizable_id'   => $digitalAsset->id,
        'category_id'        => $categories[0]->id,
    ]);
    $this->assertDatabaseHas('categorizables', [
        'categorizable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'categorizable_id'   => $digitalAsset->id,
        'category_id'        => $categories[1]->id,
    ]);
    $this->assertDatabaseMissing('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $preview->id,
    ]);
    $this->assertDatabaseMissing('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $main->id,
    ]);

    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $this->cover->id,
    ]);
    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $this->gallery->id,
    ]);
    $this->assertDatabaseHas('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $this->video->id,
    ]);

});
it('can not update a digital asset with duplicate slug', function () {
    $this->authorized_user([App\Enums\PermissionEnum::FILE_UPDATE->value]);
    $digitalAsset      = DigitalAsset::factory()->create()->fresh();
    $digitalAssetExist = DigitalAsset::factory()->create(
        ['slug' => 'existing-slug']
    )->fresh();
    $main = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('file.pdf'))
        ->toDisk('local')
        ->upload();
    $updatedData                = DigitalAsset::factory()->make()->toArray();
    $categories                 = App\Models\Category::factory()->count(2)->create();
    $updatedData['slug']        = 'existing-slug';
    $updatedData['categories']  = $categories->pluck('id')->toArray();
    $updatedData['attachments'] = [
        'main' => $main->id,
    ];
    $response = $this->putJson(route('api.v1.admin.digital-asset.update', $digitalAsset), $updatedData);
    $response->assertInvalid(['slug'])
        ->assertStatus(422);

    $this->assertDatabaseMissing('digital_assets', [
        'id'                      => $digitalAsset->id,
        'name'                    => $updatedData['name'],
        'slug'                    => 'existing-slug',
        'description'             => $updatedData['description'],
        'version'                 => $updatedData['version'],
        'page_count'              => $updatedData['page_count'],
        'duration_seconds'        => $updatedData['duration_seconds'],
        'is_attachable_to_course' => $updatedData['is_attachable_to_course'],
        'status'                  => App\Enums\PublicationStatusEnum::from($updatedData['status'])->value,
        'keywords'                => $updatedData['keywords'],
        'meta_title'              => $updatedData['meta_title'],
        'meta_description'        => $updatedData['meta_description'],
        'meta_keywords'           => $updatedData['meta_keywords'],
        'published_at'            => isset($updatedData['published_at'])
            ? $this->parseGregorianDate($updatedData['published_at'])
            : null,
    ]);
});

it('can delete digital asset', function () {
    $this->authorized_user([App\Enums\PermissionEnum::FILE_DELETE->value]);

    $digitalAsset = DigitalAsset::factory()->create()->fresh();
    $preview      = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('preview.pdf'))
        ->toDisk('local')
        ->upload();
    $main = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('file.pdf'))
        ->toDisk('local')
        ->upload();
    $digitalAsset->attachMedia($preview, 'preview');
    $digitalAsset->attachMedia($main, 'main');

    $response = $this->deleteJson(route('api.v1.admin.digital-asset.destroy', $digitalAsset));
    $response->assertStatus(204);

    $this->assertDatabaseMissing('digital_assets', [
        'id' => $digitalAsset->id,
    ]);

    $this->assertDatabaseMissing('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $preview->id,
    ]);
    $this->assertDatabaseMissing('mediables', [
        'mediable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'mediable_id'   => $digitalAsset->id,
        'media_id'      => $main->id,
    ]);
});

it('can not delete digital asset if there is related data', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::FILE_DELETE]);
    $digitalAsset = DigitalAsset::factory()->create()->fresh();
    App\Models\Product::factory()->create([
        'productable_id'   => $digitalAsset->id,
        'productable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
    ]);
    $response = $this->deleteJson(route('api.v1.admin.digital-asset.destroy', $digitalAsset->id));
    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => __('messages.errors.model_has_relationship_data',
                ['related_model' => getModelLabel(App\Models\Product::class)]),
        ]);
    $this->assertDatabaseHas('digital_assets', [
        'id' => $digitalAsset->id,
    ]);
});
