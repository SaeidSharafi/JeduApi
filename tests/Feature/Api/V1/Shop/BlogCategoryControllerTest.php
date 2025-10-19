<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;

use function Pest\Laravel\getJson;

describe('BlogCategoryController', function () {
    it('can get list of all blog categories with published posts count', function () {
        // Arrange
        $category1 = BlogCategory::factory()->create(['name' => 'Programming']);
        $category2 = BlogCategory::factory()->create(['name' => 'Web Development']);
        $category3 = BlogCategory::factory()->create(['name' => 'Mobile']);

        // Create published posts for category1
        $publishedPosts = BlogPost::factory()
            ->count(3)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        foreach ($publishedPosts as $post) {
            $post->categories()->attach($category1);
        }

        // Create draft posts for category1 (should not be counted)
        $draftPosts = BlogPost::factory()
            ->count(2)
            ->state([
                'status'       => PublicationStatusEnum::DRAFT,
                'published_at' => null,
            ])
            ->create();

        foreach ($draftPosts as $post) {
            $post->categories()->attach($category1);
        }

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.index'));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'icon',
                    'posts_count',
                ],
            ],
        ]);

        // Find category1 in response
        $categories    = $response->json('data');
        $category1Data = collect($categories)->firstWhere('id', $category1->id);

        // Only published posts should be counted
        expect($category1Data['posts_count'])->toBe(3);
    });

    it('orders categories by name', function () {
        // Arrange
        BlogCategory::factory()->create(['name' => 'Zebra Category']);
        BlogCategory::factory()->create(['name' => 'Alpha Category']);
        BlogCategory::factory()->create(['name' => 'Beta Category']);

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.index'));

        // Assert
        $response->assertOk();
        $categories = $response->json('data');

        // Verify alphabetical order
        expect($categories[0]['name'])->toBe('Alpha Category');
        expect($categories[1]['name'])->toBe('Beta Category');
        expect($categories[2]['name'])->toBe('Zebra Category');
    });

    it('can get a single blog category by slug', function () {
        // Arrange
        $category = BlogCategory::factory()->create([
            'slug'             => 'programming',
            'name'             => 'Programming',
            'description'      => 'Programming tutorials',
            'meta_title'       => 'Programming Meta Title',
            'meta_description' => 'Programming Meta Description',
            'meta_keywords'    => 'programming, coding',
        ]);

        // Create published posts for the category
        $posts = BlogPost::factory()
            ->count(5)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        foreach ($posts as $post) {
            $post->categories()->attach($category);
        }

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.show', ['slug' => $category->slug]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'description',
                'icon',
                'posts_count',
                'meta_title',
                'meta_description',
                'meta_keywords',
            ],
        ]);

        expect($response->json('data.slug'))->toBe('programming');
        expect($response->json('data.name'))->toBe('Programming');
        expect($response->json('data.posts_count'))->toBe(5);
        expect($response->json('data.meta_title'))->toBe('Programming Meta Title');
    });

    it('returns 404 when blog category slug is not found', function () {
        // Act
        $response = getJson(route('api.v1.shop.blog.categories.show', ['slug' => 'non-existent-category']));

        // Assert
        $response->assertNotFound();
    });

    it('can get posts for a specific category', function () {
        // Arrange
        $category = BlogCategory::factory()->create(['slug' => 'programming']);

        $posts = BlogPost::factory()
            ->count(5)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        foreach ($posts as $post) {
            $post->categories()->attach($category);
        }

        // Create posts in other categories
        BlogPost::factory()
            ->count(3)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.posts', ['slug' => $category->slug]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'title',
                        'slug',
                        'excerpt',
                        'author',
                        'reviews_count',
                        'average_rating',
                        'published_at',
                        'thumbnail_url',
                        'read_time_minutes',
                        'is_featured',
                        'categories',
                    ],
                ],
                'current_page',
                'per_page',
                'total',
            ],
        ]);

        // Only posts from this category
        expect($response->json('data.total'))->toBe(5);
    });

    it('can filter category posts by featured status', function () {
        // Arrange
        $category = BlogCategory::factory()->create(['slug' => 'programming']);

        $featuredPosts = BlogPost::factory()
            ->count(2)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
                'is_featured'  => true,
            ])
            ->create();

        $normalPosts = BlogPost::factory()
            ->count(3)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
                'is_featured'  => false,
            ])
            ->create();

        foreach ($featuredPosts as $post) {
            $post->categories()->attach($category);
        }

        foreach ($normalPosts as $post) {
            $post->categories()->attach($category);
        }

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.posts', [
            'slug'        => $category->slug,
            'is_featured' => true,
        ]));

        // Assert
        $response->assertOk();
        expect($response->json('data.total'))->toBe(2);

        // Verify all returned posts are featured
        $posts = $response->json('data.data');
        foreach ($posts as $post) {
            expect($post['is_featured'])->toBeTrue();
        }
    });

    it('can sort category posts by published_at', function () {
        // Arrange
        $category = BlogCategory::factory()->create(['slug' => 'programming']);

        $oldPost = BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDays(5),
            ])
            ->create();

        $newPost = BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        $oldPost->categories()->attach($category);
        $newPost->categories()->attach($category);

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.posts', [
            'slug'      => $category->slug,
            'sortBy'    => 'published_at',
            'sortOrder' => 'desc',
        ]));

        // Assert
        $response->assertOk();
        $posts = $response->json('data.data');
        expect($posts[0]['slug'])->toBe($newPost->slug);
        expect($posts[1]['slug'])->toBe($oldPost->slug);
    });

    it('respects pagination for category posts', function () {
        // Arrange
        $category = BlogCategory::factory()->create(['slug' => 'programming']);

        $posts = BlogPost::factory()
            ->count(25)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        foreach ($posts as $post) {
            $post->categories()->attach($category);
        }

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.posts', [
            'slug'     => $category->slug,
            'per_page' => 10,
            'page'     => 2,
        ]));

        // Assert
        $response->assertOk();
        expect($response->json('data.per_page'))->toBe(10);
        expect($response->json('data.current_page'))->toBe(2);
        expect($response->json('data.total'))->toBe(25);
    });

    it('returns 404 when getting posts for non-existent category', function () {
        // Act
        $response = getJson(route('api.v1.shop.blog.categories.posts', ['slug' => 'non-existent-category']));

        // Assert
        $response->assertNotFound();
    });

    it('excludes unpublished posts from category posts', function () {
        // Arrange
        $category = BlogCategory::factory()->create(['slug' => 'programming']);

        // Create published posts
        $publishedPosts = BlogPost::factory()
            ->count(3)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        // Create draft posts
        $draftPosts = BlogPost::factory()
            ->count(2)
            ->state([
                'status'       => PublicationStatusEnum::DRAFT,
                'published_at' => null,
            ])
            ->create();

        foreach ($publishedPosts as $post) {
            $post->categories()->attach($category);
        }

        foreach ($draftPosts as $post) {
            $post->categories()->attach($category);
        }

        // Act
        $response = getJson(route('api.v1.shop.blog.categories.posts', ['slug' => $category->slug]));

        // Assert
        $response->assertOk();
        // Only published posts should appear
        expect($response->json('data.total'))->toBe(3);
    });
});
