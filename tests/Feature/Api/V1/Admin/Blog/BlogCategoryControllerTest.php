<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Models\Blog\BlogCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    $this->staff = App\Models\Staff::factory()->create();
    Storage::fake('public');

    $this->media = MediaUploader::fromSource(UploadedFile::fake()->image('main1.jpg'))
        ->toDisk('public')
        ->upload();
    $this->media2 = MediaUploader::fromSource(UploadedFile::fake()->image('main2.jpg'))
        ->toDisk('public')
        ->upload();
});

describe('BlogCategoryController List & Filter', function (): void {
    it('should list categories', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_VIEW_ANY]);
        BlogCategory::factory(20)->create();
        $response = $this->getJson(route('api.v1.admin.blog.category.index'));
        $response->assertOk();
        $response->assertJsonCount(15, 'data.data');
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'icon',
                        'description',
                        'parent_id',
                        'meta_title',
                        'meta_description',
                        'meta_keywords',
                        'posts_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
    });

    it('should filter by name', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_VIEW_ANY]);
        BlogCategory::factory(20)->create();
        BlogCategory::factory()->create(['name' => 'TechX']);
        $response = $this->getJson(route('api.v1.admin.blog.category.index', ['filter' => ['name' => 'TechX']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['name' => 'TechX']);
    });

    it('should filter by slug', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_VIEW_ANY]);
        BlogCategory::factory(20)->create();
        BlogCategory::factory()->create(['slug' => 'unique-slug']);
        $response = $this->getJson(route('api.v1.admin.blog.category.index', ['filter' => ['slug' => 'unique-slug']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['slug' => 'unique-slug']);
    });

    it('should sort by name', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_VIEW_ANY]);
        BlogCategory::factory()->create(['name' => 'A']);
        BlogCategory::factory()->create(['name' => 'B']);
        $response = $this->getJson(route('api.v1.admin.blog.category.index', ['sort' => 'name']));
        $response->assertOk();
        $names = array_column($response->json('data.data'), 'name');
        expect($names[0])->toBe('A')
            ->and($names[1])->toBe('B');
    });
});

describe('BlogCategoryController CRUD', function (): void {
    it('should create a category', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_CREATE]);
        $data = BlogCategory::factory()->make([
            'icon' => $this->media ? $this->media->id : null,
        ])->toArray();
        $response = $this->postJson(route('api.v1.admin.blog.category.store'), $data);
        $response->assertCreated();
        $this->assertDatabaseHas('blog_categories', ['name' => $data['name']]);
    });

    it('should not create with missing required fields', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_CREATE]);
        $data = [
            'name'             => null,
            'slug'             => null,
            'description'      => 'This is a test category',
            'icon'             => $this->media ? $this->media->id : null,
            'meta_title'       => Str::random(70),
            'meta_description' => Str::random(100),
            'meta_keywords'    => Str::random(10).','.Str::random(10),
            'parent_id'        => null,
        ];
        $response = $this->postJson(route('api.v1.admin.blog.category.store'), $data);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    });

    it('should not create with invalid icon', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_CREATE]);
        $data     = BlogCategory::factory()->make(['icon' => 999999])->toArray();
        $response = $this->postJson(route('api.v1.admin.blog.category.store'), $data);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['icon']);
    });

    it('should not create with duplicate slug', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_CREATE]);
        $existing = BlogCategory::factory()->create(['slug' => 'dupe-slug']);
        $data     = BlogCategory::factory()->make(['slug' => 'dupe-slug'])->toArray();
        $response = $this->postJson(route('api.v1.admin.blog.category.store'), $data);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    });

    it('should show a category', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_VIEW]);
        $category = BlogCategory::factory()->create();
        $response = $this->getJson(route('api.v1.admin.blog.category.show', ['category' => $category]));
        $response->assertOk();
        $response->assertJsonFragment(['name' => $category->name]);
    });

    it('should return 404 for non-existent category', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_VIEW]);
        $response = $this->getJson(route('api.v1.admin.blog.category.show', ['category' => 999999]));
        $response->assertNotFound();
    });

    it('should update a category', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_UPDATE]);
        $category = BlogCategory::factory()->create();
        $data     = BlogCategory::factory()->make([
            'icon' => $this->media2 ? $this->media2->id : null,
        ])->toArray();
        $response = $this->putJson(route('api.v1.admin.blog.category.update', ['category' => $category]), $data);
        $response->assertOk();
        $this->assertDatabaseHas('blog_categories', ['id' => $category->id, 'name' => $data['name']]);
    });

    it('should not update with invalid icon', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_UPDATE]);
        $category = BlogCategory::factory()->create();
        $data     = BlogCategory::factory()->make(['icon' => 999999])->toArray();
        $response = $this->putJson(route('api.v1.admin.blog.category.update', ['category' => $category]), $data);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['icon']);
    });

    it('should not update with missing required fields', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_UPDATE]);
        $category = BlogCategory::factory()->create();
        $response = $this->putJson(route('api.v1.admin.blog.category.update', ['category' => $category]), []);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    });

    it('should delete a category', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_DELETE]);
        $category = BlogCategory::factory()->create();
        $response = $this->deleteJson(route('api.v1.admin.blog.category.destroy', ['category' => $category]));
        $response->assertOk();
        $this->assertDatabaseMissing('blog_categories', ['id' => $category->id]);
    });

    it('should return 404 for non-existent category on delete', function (): void {
        $this->authorized_user([PermissionEnum::BLOG_CATEGORY_DELETE]);
        $response = $this->deleteJson(route('api.v1.admin.blog.category.destroy', ['category' => 999999]));
        $response->assertNotFound();
    });

    it('should not allow unauthorized access', function (): void {
        $this->unauthorized_user();
        $category = BlogCategory::factory()->create();
        $data     = BlogCategory::factory()->make([
            'icon' => $this->media ? $this->media->id : null,
        ])->toArray();

        // Index
        $response = $this->getJson(route('api.v1.admin.blog.category.index'));
        $response->assertForbidden();

        // Store
        $response = $this->postJson(route('api.v1.admin.blog.category.store'), $data);
        $response->assertForbidden();

        // Show
        $response = $this->getJson(route('api.v1.admin.blog.category.show', ['category' => $category]));
        $response->assertForbidden();

        // Update
        $response = $this->putJson(route('api.v1.admin.blog.category.update', ['category' => $category]), $data);
        $response->assertForbidden();

        // Delete
        $response = $this->deleteJson(route('api.v1.admin.blog.category.destroy', ['category' => $category]));
        $response->assertForbidden();
    });

});
