<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Staff;

use function Pest\Laravel\getJson;

describe('BlogPostController', function () {
    it('can get list of published blog posts', function () {
        Storage::fake('public');
        $this->fakeMedia();
        // Arrange - Create published and unpublished posts
        $publishedPosts = BlogPost::factory()
            ->count(3)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->withMedia()
            ->create();

        // Create unpublished posts that should not appear
        BlogPost::factory()
            ->count(2)
            ->state([
                'status'       => PublicationStatusEnum::DRAFT,
                'published_at' => null,
            ])
            ->create();

        // Create future published posts that should not appear
        BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->addDay(),
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.index'));

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
                        'media'
                    ],
                ],
                'current_page',
                'per_page',
                'total',
            ],
        ]);
        // Only published posts should appear
        expect($response->json('data.total'))->toBe(3)
            ->and($response->json('data.data.0.media'))->toHaveCount(1)
            ->and($response->json('data.data.0.media.cover'))->toHaveCount(1)
        ;
    });

    it('can filter blog posts by featured status', function () {
        // Arrange
        $featuredPosts = BlogPost::factory()
            ->count(2)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
                'is_featured'  => true,
            ])
            ->create();

        BlogPost::factory()
            ->count(3)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
                'is_featured'  => false,
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.index', ['is_featured' => true]));

        // Assert
        $response->assertOk();
        expect($response->json('data.total'))->toBe(2);

        // Verify all returned posts are featured
        $posts = $response->json('data.data');
        foreach ($posts as $post) {
            expect($post['is_featured'])->toBeTrue();
        }
    });

    it('can filter blog posts by category slug', function () {
        // Arrange
        $category = BlogCategory::factory()->create(['slug' => 'programming']);
        $posts    = BlogPost::factory()
            ->count(3)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        // Attach posts to category
        foreach ($posts as $post) {
            $post->categories()->attach($category);
        }

        // Create posts in different category
        BlogPost::factory()
            ->count(2)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.index', ['category_slug' => 'programming']));

        // Assert
        $response->assertOk();
        expect($response->json('data.total'))->toBe(3);
    });

    it('can sort blog posts by published_at', function () {
        // Arrange
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

        // Act - Sort descending (newest first)
        $response = getJson(route('api.v1.shop.blog.posts.index', [
            'sortBy'    => 'published_at',
            'sortOrder' => 'desc',
        ]));

        // Assert
        $response->assertOk();
        $posts = $response->json('data.data');
        expect($posts[0]['slug'])->toBe($newPost->slug);
        expect($posts[1]['slug'])->toBe($oldPost->slug);
    });

    it('can sort blog posts by created_at ascending', function () {
        // Arrange
        $oldPost = BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
                'created_at'   => now()->subDays(10),
            ])
            ->create();

        $newPost = BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
                'created_at'   => now()->subDays(5),
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.index', [
            'sortBy'    => 'created_at',
            'sortOrder' => 'asc',
        ]));

        // Assert
        $response->assertOk();
        $posts = $response->json('data.data');
        expect($posts[0]['slug'])->toBe($oldPost->slug);
        expect($posts[1]['slug'])->toBe($newPost->slug);
    });

    it('respects pagination parameters', function () {
        // Arrange
        BlogPost::factory()
            ->count(25)
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.index', [
            'per_page' => 10,
            'page'     => 2,
        ]));

        // Assert
        $response->assertOk();
        expect($response->json('data.per_page'))->toBe(10);
        expect($response->json('data.current_page'))->toBe(2);
        expect($response->json('data.total'))->toBe(25);
    });

    it('can get a single published blog post by slug', function () {
        // Arrange
        $category = BlogCategory::factory()->create();
        $staff    = Staff::factory()->create(['name' => 'John Doe']);
        $post     = BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->subDay(),
                'author_id'    => $staff->id,
            ])
            ->create();

        $post->categories()->attach($category);

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.show', ['slug' => $post->slug]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'title',
                'slug',
                'body',
                'excerpt',
                'author' => [
                    'name',
                ],
                'reviews_count',
                'average_rating',
                'published_at',
                'thumbnail_url',
                'read_time_minutes',
                'is_featured',
                'categories' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'icon',
                        'posts_count',
                    ],
                ],
                'media',
                'meta_title',
                'meta_description',
                'meta_keywords',
            ],
        ]);

        expect($response->json('data.slug'))->toBe($post->slug);
        expect($response->json('data.author.name'))->toBe('John Doe');
    });

    it('returns 404 when blog post slug is not found', function () {
        // Act
        $response = getJson(route('api.v1.shop.blog.posts.show', ['slug' => 'non-existent-slug']));

        // Assert
        $response->assertNotFound();
    });

    it('returns 404 when accessing unpublished blog post', function () {
        // Arrange
        $post = BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::DRAFT,
                'published_at' => null,
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.show', ['slug' => $post->slug]));

        // Assert
        $response->assertNotFound();
    });

    it('returns 404 when accessing future published blog post', function () {
        // Arrange
        $post = BlogPost::factory()
            ->state([
                'status'       => PublicationStatusEnum::PUBLISHED,
                'published_at' => now()->addDay(),
            ])
            ->create();

        // Act
        $response = getJson(route('api.v1.shop.blog.posts.show', ['slug' => $post->slug]));

        // Assert
        $response->assertNotFound();
    });

    it('validates invalid sort field', function () {
        // Act
        $response = getJson(route('api.v1.shop.blog.posts.index', [
            'sortBy' => 'invalid_field',
        ]));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sortBy']);
    });

    it('validates invalid category slug', function () {
        // Act
        $response = getJson(route('api.v1.shop.blog.posts.index', [
            'category_slug' => 'non-existent-category',
        ]));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_slug']);
    });
});
