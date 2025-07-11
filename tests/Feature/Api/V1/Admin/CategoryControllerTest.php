<?php

declare(strict_types=1);

uses(Tests\AuthTestTrait::class);
it('can get list of categories', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_VIEW_ANY->value]);
    App\Models\Course::factory()->count(10)->create();
    $response = $this->getJson(route('api.v1.admin.category.index'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'image_url',
                        'icon_url',
                        'color_scheme',
                        'status',
                        'created_by',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
});

it('can get single category', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_VIEW->value]);
    $category = App\Models\Category::factory()->create();
    $response = $this->getJson(route('api.v1.admin.category.show', ['category' => $category->id]));
    $response->assertStatus(200)
        ->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($category): void {
            $json->where('data.id', $category->id)
                ->where('data.name', $category->name)
                ->where('data.slug', $category->slug)
                ->where('data.description', $category->description)
                ->where('data.color_scheme', $category->color_scheme)
                ->where('data.status', [
                    'value' => $category->status->value,
                    'label' => $category->status->translate(),
                ])
                ->etc();
        });
});

it('can create category', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_CREATE->value]);
    Storage::fake('public');
    $icon = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon.jpg'))
        ->toDisk('public')
        ->upload();
    $image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();
    $category = App\Models\Category::factory()->make();
    $response = $this->postJson(route('api.v1.admin.category.store'), [
        'name'             => $category->name,
        'slug'             => $category->slug,
        'status'           => $category->status,
        'parent_id'        => $category->parent_id,
        'description'      => $category->description,
        'color_scheme'     => $category->color_scheme,
        'meta_title'       => $category->meta_title,
        'meta_description' => $category->meta_description,
        'meta_keywords'    => $category->meta_keywords,
        'properties'       => $category->properties,
        'additional_info'  => $category->additional_info,
        'media'            => [
            'icon'  => $icon->id,
            'image' => $image->id,
        ],
    ]);
    $response->assertStatus(201);

    $this->assertDatabaseHas('categories', [
        'name'             => $category->name,
        'slug'             => $category->slug,
        'status'           => $category->status->value,
        'parent_id'        => $category->parent_id,
        'description'      => $category->description,
        'color_scheme'     => $category->color_scheme,
        'meta_title'       => $category->meta_title,
        'meta_description' => $category->meta_description,
        'meta_keywords'    => $category->meta_keywords,
    ]);
});

it('can update category', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_UPDATE->value]);
    Storage::fake('public');
    $icon = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('icon.jpg'))
        ->toDisk('public')
        ->upload();
    $image = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('image.jpg'))
        ->toDisk('public')
        ->upload();
    $category = App\Models\Category::factory()->create();
    $response = $this->putJson(route('api.v1.admin.category.update', ['category' => $category->id]), [
        'name'             => 'Updated Category',
        'slug'             => 'updated-category',
        'status'           => App\Enums\PublicationStatusEnum::DRAFT,
        'parent_id'        => null,
        'description'      => 'Updated description',
        'color_scheme'     => '#000000',
        'meta_title'       => 'Updated meta title',
        'meta_description' => 'Updated meta description lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'meta_keywords'    => 'updated,meta,keywords',
        'properties'       => ['key1' => 'value1'],
        'additional_info'  => ['info1' => 'value1'],
        'media'            => [
            'icon'  => $icon->id,
            'image' => $image->id,
        ],
    ]);
    $response->assertStatus(200);

    $this->assertDatabaseHas('categories', [
        'name'             => 'Updated Category',
        'slug'             => 'updated-category',
        'status'           => App\Enums\PublicationStatusEnum::DRAFT->value,
        'parent_id'        => null,
        'description'      => 'Updated description',
        'color_scheme'     => '#000000',
        'meta_title'       => 'Updated meta title',
        'meta_description' => 'Updated meta description lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'meta_keywords'    => 'updated,meta,keywords',
    ]);
});

it('can delete category', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_DELETE->value]);
    $category = App\Models\Category::factory()->create();
    $response = $this->deleteJson(route('api.v1.admin.category.destroy', ['category' => $category->id]));
    $response->assertStatus(204);
    $this->assertDatabaseMissing('categories', [
        'id' => $category->id,
    ]);
});
it('can not delete category if there is related data', function (): void {
    $this->authorized_user([App\Enums\PermissionEnum::CATEGORY_DELETE->value]);
    $category = App\Models\Category::factory()->create();
    $product  = App\Models\Product::factory()->create();
    $product->categories()->attach($category->id);
    $response = $this->deleteJson(route('api.v1.admin.category.destroy', ['category' => $category->id]));
    $response->assertStatus(422)
        ->assertJsonFragment(['message' => __('messages.errors.model_has_relationship_data_without_related_model')]);
    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
    ]);
});
it('can not access category without auth', function (): void {
    $this->unauthorized_user();
    $category = App\Models\Category::factory()->create();
    $response = $this->getJson(route('api.v1.admin.category.show', ['category' => $category->id]));
    $response->assertStatus(403);

    $response = $this->postJson(route('api.v1.admin.category.store'), [
        'name'             => 'Test Category',
        'slug'             => 'test-category',
        'status'           => App\Enums\PublicationStatusEnum::DRAFT,
        'parent_id'        => null,
        'description'      => 'Test description',
        'color_scheme'     => '#000000',
        'meta_title'       => 'Test meta title',
        'meta_description' => 'Updated meta description lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'meta_keywords'    => 'test,meta,keywords',
        'properties'       => ['key1' => 'value1'],
        'additional_info'  => ['info1' => 'value1'],
    ]);
    $response->assertStatus(403);
    $response = $this->putJson(route('api.v1.admin.category.update', ['category' => $category->id]), [
        'name'             => 'Updated Category',
        'slug'             => 'updated-category',
        'status'           => App\Enums\PublicationStatusEnum::DRAFT,
        'parent_id'        => null,
        'description'      => 'Updated description',
        'color_scheme'     => '#000000',
        'meta_title'       => 'Updated meta title',
        'meta_description' => 'Updated meta description lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'meta_keywords'    => 'updated,meta,keywords',
        'properties'       => ['key1' => 'value1'],
        'additional_info'  => ['info1' => 'value1'],
    ]);
    $response->assertStatus(403);
    $response = $this->deleteJson(route('api.v1.admin.category.destroy', ['category' => $category->id]));
    $response->assertStatus(403);

});
