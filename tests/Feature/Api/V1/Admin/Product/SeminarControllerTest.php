<?php

declare(strict_types=1);

use App\Models\Category;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
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
    it('should filter by full name', function (): void {
        App\Models\Seminar::factory(10)->create();
        App\Models\Seminar::factory()->create(['full_name' => 'Test Seminar']);
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index', [
            'filter' => [
                'full_name' => 'Test Seminar',
            ],
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['full_name' => 'Test Seminar']);
    });
    it('should filter by slug', function (): void {
        $seminar = App\Models\Seminar::factory()->create(['slug' => 'test-seminar']);
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index', ['filter' => ['slug' => 'test-seminar']]));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['slug' => 'test-seminar']);
    });
    it('should filter by short name', function (): void {
        $seminar = App\Models\Seminar::factory()->create(['short_name' => 'Short Seminar']);
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index',
            ['filter' => ['short_name' => 'Short Seminar']]));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['short_name' => 'Short Seminar']);
    });
    it('should return empty when no seminars match the filter', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index',
            ['filter' => ['full_name' => 'Nonexistent Seminar']]));
        $response->assertOk()
            ->assertJsonCount(0, 'data.data');
    });
    it('should return all seminars when no filter is applied', function (): void {
        $seminars = App\Models\Seminar::factory()->count(3)->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index'));
        $response->assertOk()
            ->assertJsonCount(3, 'data.data')
            ->assertJsonFragment(['full_name' => $seminars[0]->full_name])
            ->assertJsonFragment(['full_name' => $seminars[1]->full_name])
            ->assertJsonFragment(['full_name' => $seminars[2]->full_name]);
    });
    it('should return seminars with pagination', function (): void {
        App\Models\Seminar::factory()->count(20)->create();
        $seminars = App\Models\Seminar::query()->orderBy('created_at', 'desc')->get();
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index', ['per_page' => 10]));
        $response->assertOk()
            ->assertJsonCount(10, 'data.data')
            ->assertJsonFragment(['full_name' => $seminars[0]->full_name])
            ->assertJsonFragment(['full_name' => $seminars[9]->full_name])
            ->assertJsonMissing(['full_name' => $seminars[10]->full_name]);
    });
    it('should sort by full name', function (): void {
        $seminar1 = App\Models\Seminar::factory()->create(['full_name' => 'A Seminar']);
        $seminar2 = App\Models\Seminar::factory()->create(['full_name' => 'B Seminar']);
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index', ['sort' => 'full_name']));
        $response->assertOk()
            ->assertJsonFragment(['full_name' => 'A Seminar'])
            ->assertJsonFragment(['full_name' => 'B Seminar'])
            ->assertJsonPath('data.data.0.full_name', 'A Seminar')
            ->assertJsonPath('data.data.1.full_name', 'B Seminar');
    });
    it('should sort by created at', function (): void {
        $seminar1 = App\Models\Seminar::factory()->create(['created_at' => now()->subDays(2)]);
        $seminar2 = App\Models\Seminar::factory()->create(['created_at' => now()->subDays(1)]);
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index', ['sort' => 'created_at']));
        $response->assertOk()
            ->assertJsonPath('data.data.0.full_name', $seminar1->full_name)
            ->assertJsonPath('data.data.1.full_name', $seminar2->full_name);
    });
    it('should sort by updated at', function (): void {
        $seminar1 = App\Models\Seminar::factory()->create(['updated_at' => now()->subDays(2)]);
        $seminar2 = App\Models\Seminar::factory()->create(['updated_at' => now()->subDays(1)]);
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);
        $response = $this->getJson(route('api.v1.admin.seminar.index', ['sort' => 'updated_at']));
        $response->assertOk()
            ->assertJsonPath('data.data.0.full_name', $seminar1->full_name)
            ->assertJsonPath('data.data.1.full_name', $seminar2->full_name);
    });
});

describe('SeminarController', function (): void {
    it('should list seminars with categories and digital assets', function (): void {
        $seminars = App\Models\Seminar::factory(10)
            ->withCategory(3)
            ->withDigitalAssets()
            ->create()
            ->fresh();
        $seminars->load('categories', 'digitalAssets');
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW_ANY->value,
        ]);

        $response = $this->getJson(route('api.v1.admin.seminar.index'));

        $response->assertOk();
        $response
            ->assertJsonCount(10, 'data.data')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'full_name',
                            'short_name',
                            'slug',
                            'thumbnail_url',
                            'status',
                            'created_by',
                            'created_at',
                            'updated_at',
                            'categories' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'slug',
                                    'status',
                                    'image_url',
                                    'icon_url',
                                    'created_by',
                                    'created_at',
                                    'updated_at',
                                ],
                            ],
                            'digital_assets' => [
                                '*' => [
                                    'id',
                                    'short_name',
                                    'slug',
                                    'is_attachable_to_course',
                                    'status',
                                    'version',
                                    'published_at',
                                    'created_by',
                                    'created_at',
                                    'updated_at',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        $actualDataItems = collect($response->json('data.data'));

        foreach ($seminars as $expectedSeminar) {
            $match = $actualDataItems->firstWhere('slug', $expectedSeminar->slug);

            expect($match)->not->toBeNull("Expected course with slug '{$expectedSeminar->slug}' not found or properties mismatch.");

            if ($match) {
                expect($match['full_name'])->toBe($expectedSeminar->full_name)
                    ->and($match['short_name'])->toBe($expectedSeminar->short_name)
                    ->and($match['difficulty_level']['value'])->toBe($expectedSeminar->difficulty_level->value)
                    ->and($match['status']['value'])->toBe($expectedSeminar->status->value)
                    ->and($match['created_by'])->toBe($expectedSeminar->created_by)
                    ->and($match['categories'])->toHaveCount($expectedSeminar->categories->count())
                    ->and($match['digital_assets'])->toHaveCount($expectedSeminar->digitalAssets->count());
                $actualCategories = collect($match['categories']);
                expect($actualCategories)->toHaveCount($expectedSeminar->categories->count());
                foreach ($expectedSeminar->categories as $expectedCategory) {
                    $categoryMatch = $actualCategories->firstWhere('slug', $expectedCategory->slug);

                    expect($categoryMatch)->not->toBeNull("For seminar '{$expectedSeminar->slug}', expected category '{$expectedCategory->slug}' was not found.");

                    if ($categoryMatch) {
                        expect($categoryMatch['name'])->toBe($expectedCategory->name);
                    }
                }
                $actualDigitalAssets = collect($match['digital_assets']);
                expect($actualDigitalAssets)->toHaveCount($expectedSeminar->digitalAssets->count());
                foreach ($expectedSeminar->digitalAssets as $expectedAsset) {
                    $assetMatch = $actualDigitalAssets->firstWhere('slug', $expectedAsset->slug);

                    expect($assetMatch)->not->toBeNull("For seminar '{$expectedSeminar->slug}', expected asset '{$expectedAsset->slug}' was not found.");
                }
            }
        }
    });

    it('should show a seminar with categories and digital assets', function (): void {
        $seminar       = App\Models\Seminar::factory()->create();
        $digitalAssets = App\Models\DigitalAsset::factory(2)->create();
        $categories    = Category::factory(3)->create();
        $seminar->digitalAssets()->attach($digitalAssets);
        $seminar->categories()->attach($categories);
        $sortedCategories = $categories->sortBy('id')->values();
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_VIEW->value,
        ]);

        $response = $this->getJson(route('api.v1.admin.seminar.show', ['seminar' => $seminar->id]));
        $response->assertOk();
        $response->assertJsonCount(2, 'data.digital_assets');
        $response->assertJsonCount(3, 'data.categories');
        foreach ($digitalAssets as $asset) {
            $response->assertJsonFragment([
                'id'         => $asset->id,
                'short_name' => $asset->short_name,
                'slug'       => $asset->slug,
                'version'    => $asset->version,
            ]);
        }
        foreach ($categories as $category) {
            $response->assertJsonFragment([
                'id'   => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);
        }
    });

    it('should create a seminar', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_CREATE->value,
        ]);

        $seminarData                   = App\Models\Seminar::factory()->make()->toArray();
        $cateogires                    = Category::factory()->count(2)->create();
        $seminarData['categories']     = $cateogires->pluck('id')->toArray();
        $seminarData['digital_assets'] = App\Models\DigitalAsset::factory()->count(2)->create()->pluck('id')->toArray();

        $response = $this->postJson(route('api.v1.admin.seminar.store'), [
            ...$seminarData,
            'media' => [
                'cover'   => [$this->cover->id],
                'gallery' => [$this->gallery->id],
                'video'   => [$this->video->id],
            ],
        ]);

        $response->assertCreated();
        $seminar = App\Models\Seminar::first();
        $this->assertDatabaseCount('seminars', 1);
        $this->assertDatabaseHas('seminars', [
            'full_name'     => $seminarData['full_name'],
            'short_name'    => $seminarData['short_name'],
            'slug'          => $seminarData['slug'],
            'thumbnail_url' => $this->cover->getUrl(),
        ]);
        $this->assertDatabaseHas('categorizables',
            [
                'categorizable_id'   => $seminar->id,
                'categorizable_type' => App\Enums\System\MorphTypeEnum::SEMINAR->value,
                'category_id'        => $cateogires[0]->id,
            ]
        );
        $this->assertDatabaseHas('categorizables',
            [
                'categorizable_id'   => $seminar->id,
                'categorizable_type' => App\Enums\System\MorphTypeEnum::SEMINAR->value,
                'category_id'        => $cateogires[1]->id,
            ]
        );

        $this->assertDatabaseHas('assetables',
            [
                'assetable_id'     => $seminar->id,
                'assetable_type'   => App\Enums\System\MorphTypeEnum::SEMINAR->value,
                'digital_asset_id' => $seminarData['digital_assets'][0],
            ]
        );
        $this->assertDatabaseHas('assetables',
            [
                'assetable_id'     => $seminar->id,
                'assetable_type'   => App\Enums\System\MorphTypeEnum::SEMINAR->value,
                'digital_asset_id' => $seminarData['digital_assets'][1],
            ]
        );
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $seminar->id,
            'mediable_type' => App\Enums\System\MorphTypeEnum::SEMINAR->value,
            'media_id'      => $this->cover->id,
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $seminar->id,
            'mediable_type' => App\Enums\System\MorphTypeEnum::SEMINAR->value,
            'media_id'      => $this->gallery->id,
        ]);
        $this->assertDatabaseHas('mediables', [
            'mediable_id'   => $seminar->id,
            'mediable_type' => App\Enums\System\MorphTypeEnum::SEMINAR->value,
            'media_id'      => $this->video->id,
        ]);
    });

    it('should not create a seminar without required permissions', function (): void {
        $seminarData = App\Models\Seminar::factory()->make()->toArray();
        $response    = $this->postJson(route('api.v1.admin.seminar.store'), $seminarData);

        $response->assertUnauthorized();
    });

    it('should not list seminars without required permissions', function (): void {
        $response = $this->getJson(route('api.v1.admin.seminar.index'));

        $response->assertUnauthorized();
    });

    it('should not show a seminar without required permissions', function (): void {
        $seminar  = App\Models\Seminar::factory()->create();
        $response = $this->getJson(route('api.v1.admin.seminar.show', ['seminar' => $seminar->id]));

        $response->assertUnauthorized();
    });

    it('should not create a seminar with invalid data', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_CREATE->value,
        ]);

        $response = $this->postJson(route('api.v1.admin.seminar.store'), [
            'full_name' => '', // Invalid data
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name']);
    });

    it('should not update a seminar without required permissions', function (): void {
        $seminar  = App\Models\Seminar::factory()->create();
        $response = $this->putJson(route('api.v1.admin.seminar.update', ['seminar' => $seminar->id]), [
            'full_name' => 'Updated Seminar Name',
        ]);

        $response->assertUnauthorized();
    });

    it('should update a seminar with valid data', function (): void {
        $seminar = App\Models\Seminar::factory()
            ->withCategory()
            ->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_UPDATE->value,
        ]);
        $categories = Category::factory()->count(5)->create();
        $category   = $categories->first();
        $response   = $this->putJson(route('api.v1.admin.seminar.update', ['seminar' => $seminar->id]), [
            ...$seminar->toArray(),
            'full_name'      => 'Updated Seminar Name',
            'short_name'     => 'Updated Short Name',
            'categories'     => [$category->id],
            'digital_assets' => App\Models\DigitalAsset::factory()->count(2)->create()->pluck('id')->toArray(),
            'media'          => [
                'cover'   => [$this->cover->id],
                'gallery' => [$this->gallery->id],
                'video'   => [$this->video->id],
            ],
        ]);

        $response->assertOk()
            ->assertJsonFragment(['full_name' => 'Updated Seminar Name'])
            ->assertJsonFragment(['short_name' => 'Updated Short Name']);

        $this->assertDatabaseHas('seminars', [
            'id'            => $seminar->id,
            'full_name'     => 'Updated Seminar Name',
            'short_name'    => 'Updated Short Name',
            'thumbnail_url' => $this->cover->getUrl(),
        ]);
    });

    it('should delete a seminar with required permissions', function (): void {
        $seminar = App\Models\Seminar::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_DELETE->value,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.seminar.destroy', ['seminar' => $seminar->id]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('seminars', ['id' => $seminar->id]);
    });
    it('should not delete a seminar without required permissions', function (): void {
        $seminar  = App\Models\Seminar::factory()->create();
        $response = $this->deleteJson(route('api.v1.admin.seminar.destroy', ['seminar' => $seminar->id]));

        $response->assertUnauthorized();
    });
    it('should not delete a seminar that does not exist', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_DELETE->value,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.seminar.destroy', ['seminar' => 999]));

        $response->assertNotFound();
    });
    it('should delete not a seminar with related data', function (): void {
        $seminar = App\Models\Seminar::factory()->create();
        App\Models\Product::factory()->create([
            'productable_id'   => $seminar->id,
            'productable_type' => App\Enums\System\MorphTypeEnum::SEMINAR->value,
        ]);
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_DELETE->value,
        ]);

        $response = $this->deleteJson(route('api.v1.admin.seminar.destroy', ['seminar' => $seminar->id]));
        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('messages.errors.model_has_relationship_data',
                    ['related_model' => getModelLabel(App\Models\Product::class)]),
            ]);
        $this->assertDatabaseHas('seminars', ['id' => $seminar->id]);
    });

    it('should not update a seminar that does not exist', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_UPDATE->value,
        ]);

        $response = $this->putJson(route('api.v1.admin.seminar.update', ['seminar' => 999]), [
            'full_name' => 'Updated Seminar Name',
        ]);

        $response->assertNotFound();
    });
    it('should not update a seminar with invalid data', function (): void {
        $seminar = App\Models\Seminar::factory()->create();
        $this->authorized_user([
            App\Enums\PermissionEnum::SEMINAR_UPDATE->value,
        ]);

        $response = $this->putJson(route('api.v1.admin.seminar.update', ['seminar' => $seminar->id]), [
            'full_name' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name']);
    });

});
