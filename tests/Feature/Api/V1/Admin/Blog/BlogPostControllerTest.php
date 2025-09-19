<?php

use App\Enums\MorphTypeEnum;
use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;

uses(\Tests\AuthTestTrait::class);
describe('BlogPostController List & Filter', function () {
    it('should list posts', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW_ANY]);
        \App\Models\Blog\BlogPost::factory(20)->create();
        $response = $this->getJson(route('api.v1.admin.blog.post.index'));
        $response->assertOk();
        $response->assertJsonCount(15, 'data.data');
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'excerpt',
                        'author_id',
                        'status',
                        'read_time_minutes',
                        'is_featured',
                        'categories' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                                'created_at',
                                'updated_at',
                            ],
                        ],
                        'author'     => [
                            'id',
                            'name',
                            'email',
                            'phone',
                        ],
                        'thumbnail_url',
                        'published_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
    });

    it('should filter by title', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW_ANY]);
        \App\Models\Blog\BlogPost::factory(20)->create();
        \App\Models\Blog\BlogPost::factory()->create(['title' => 'TechX Innovations']);
        $response = $this->getJson(route('api.v1.admin.blog.post.index',
            ['filter' => ['title' => 'TechX Innovations']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['title' => 'TechX Innovations']);
    });

    it('should filter by slug', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW_ANY]);
        \App\Models\Blog\BlogPost::factory(20)->create();
        \App\Models\Blog\BlogPost::factory()->create(['slug' => 'unique-post-slug']);
        $response = $this->getJson(route('api.v1.admin.blog.post.index', ['filter' => ['slug' => 'unique-post-slug']]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['slug' => 'unique-post-slug']);
    });

    it('should filter by published status', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW_ANY]);
        \App\Models\Blog\BlogPost::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
        \App\Models\Blog\BlogPost::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $response = $this->getJson(route('api.v1.admin.blog.post.index',
            ['filter' => ['status' => PublicationStatusEnum::PUBLISHED->value]]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['status' => PublicationStatusEnum::PUBLISHED->value]);
    });

    it('should filter by author_id', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW_ANY]);
        $author = \App\Models\Staff::factory()->create();
        \App\Models\Blog\BlogPost::factory()->create(['author_id' => $author->id]);
        \App\Models\Blog\BlogPost::factory()->create();
        $response = $this->getJson(route('api.v1.admin.blog.post.index', ['filter' => ['author_id' => $author->id]]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['author_id' => $author->id]);
    });
    it('should filter by main productable type', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW_ANY]);
        $course = \App\Models\Course::factory()->create();
        $postWithCourse = \App\Models\Blog\BlogPost::factory()->create();
        $postWithCourse->courses()->attach($course->id);
        $postWithCourse->main_productable_type = ProductableEnum::COURSE->value;
        $postWithCourse->main_productable_id = $course->id;
        $postWithCourse->save();

        \App\Models\Blog\BlogPost::factory()->count(5)->create([
            'main_productable_type' => ProductableEnum::SEMINAR->value,
            'main_productable_id'   => \App\Models\Seminar::factory(),
        ]);
        $response = $this->getJson(route('api.v1.admin.blog.post.index', [
            'filter' => [
                'main_productable_type' => ProductableEnum::COURSE->value,
            ],
        ]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['id' => $postWithCourse->id]);
    });
    it('should filter by main productable type and id', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW_ANY]);
        $course = \App\Models\Course::factory()->create();
        $postWithCourse = \App\Models\Blog\BlogPost::factory()->create();
        $postWithCourse->courses()->attach($course->id);
        $postWithCourse->main_productable_type = ProductableEnum::COURSE->value;
        $postWithCourse->main_productable_id = $course->id;
        $postWithCourse->save();

        \App\Models\Blog\BlogPost::factory()->count(5)->create();
        $response = $this->getJson(route('api.v1.admin.blog.post.index', [
            'filter' => [
                'main_productable_type' => ProductableEnum::COURSE->value,
                'main_productable_id'   => $course->id,
            ],
        ]));
        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['id' => $postWithCourse->id]);
    });

});

describe('BlogPostController CRUD', function () {
    beforeEach(function () {
        Storage::fake('public');

        $this->cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
            ->toDisk('public')
            ->upload();
        $this->video = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()
            ->create('video.mp4', 5000, 'video/mp4'))
            ->toDisk('public')
            ->upload();
    });

    it('should create a post', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_CREATE]);
        $category = \App\Models\Blog\BlogCategory::factory()->create();
        $author = \App\Models\Staff::factory()->create();
        $postData = [
            'title'        => 'New Blog Post',
            'slug'         => 'new-blog-post',
            'excerpt'      => 'This is a new blog post.',
            'body'         => 'Full content of the new blog post.',
            'status'       => PublicationStatusEnum::DRAFT->value,
            'published_at' => now()->toDateTimeString(),
            'author_id'    => $author->id,
            'category_ids' => [$category->id],
            'is_featured'  => false,
            'media'        => [
                'cover' => [$this->cover->id],
                'video' => [$this->video->id],
            ],
        ];

        $response = $this->postJson(route('api.v1.admin.blog.post.store'), $postData);
        $response->assertCreated();
        $response->assertJsonFragment(['title' => 'New Blog Post']);
        $responseData = $response->json('data');
        expect($responseData['title'])->toBe('New Blog Post')
            ->and($responseData['slug'])->toBe('new-blog-post')
            ->and($responseData['author_id'])->toBe($author->id)
            ->and($responseData['status'])->toBe(PublicationStatusEnum::DRAFT->value)
            ->and($responseData['excerpt'])->toBe('This is a new blog post.')
            ->and($responseData['body'])->toBe('Full content of the new blog post.')
            ->and($responseData['is_featured'])->toBeFalse()
            ->and($responseData['categories'][0]['id'])->toBe($category->id)
            ->and($responseData['author']['id'])->toBe($author->id)
            ->and(count($responseData['media']['cover']))->toBe(1)
            ->and($responseData['media']['cover'][0]['id'])->toBe($this->cover->id)
            ->and(count($responseData['media']['video']))->toBe(1)
            ->and($responseData['media']['video'][0]['id'])->toBe($this->video->id);

        $this->assertDatabaseHas('blog_posts', ['slug' => 'new-blog-post']);
        $this->assertDatabaseHas('blog_post_category', [
            'blog_post_id'     => $response->json('data.id'),
            'blog_category_id' => $category->id,
        ]);
        $this->assertDatabaseHas('mediables', [
            'media_id'      => $this->cover->id,
            'mediable_id'   => $response->json('data.id'),
            'mediable_type' => MorphTypeEnum::BLOG_POST->value,
            'tag'           => 'cover',
        ]);
        $this->assertDatabaseHas('mediables', [
            'media_id'      => $this->video->id,
            'mediable_id'   => $response->json('data.id'),
            'mediable_type' => MorphTypeEnum::BLOG_POST->value,
            'tag'           => 'video',
        ]);
    });

    it('should show a post', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_VIEW]);
        $post = \App\Models\Blog\BlogPost::factory()->create();
        $response = $this->getJson(route('api.v1.admin.blog.post.show', ['post' => $post->id]));
        $response->assertOk();
        $response->assertJsonFragment(['id' => $post->id, 'title' => $post->title]);
    });

    it('should update a post', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_UPDATE]);
        $post = \App\Models\Blog\BlogPost::factory()->create(['title' => 'Old Title']);
        $updateData = [
            'title'   => 'Updated Blog Post Title',
            'excerpt' => 'Updated excerpt.',
            'body'    => 'Updated full content.',
            'status'  => PublicationStatusEnum::PUBLISHED->value,
            'media'        => [
                'cover' => [$this->cover->id],
            ],

        ];
        $response = $this->putJson(route('api.v1.admin.blog.post.update', ['post' => $post->id]), $updateData);
        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Updated Blog Post Title']);
        $responseData = $response->json('data');
        expect($responseData['title'])->toBe('Updated Blog Post Title')
            ->and($responseData['excerpt'])->toBe('Updated excerpt.')
            ->and($responseData['body'])->toBe('Updated full content.')
            ->and($responseData['status'])->toBe(PublicationStatusEnum::PUBLISHED->value)
            ->and(count($responseData['media']['cover']))->toBe(1)
            ->and($responseData['media']['cover'][0]['id'])->toBe($this->cover->id);
        $this->assertDatabaseHas('blog_posts', ['id' => $post->id, 'title' => 'Updated Blog Post Title']);
    });
    it('should delete a post', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::BLOG_POST_DELETE]);
        $post = \App\Models\Blog\BlogPost::factory()->create();
        $response = $this->deleteJson(route('api.v1.admin.blog.post.destroy', ['post' => $post->id]));
        $response->assertNoContent();
        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    });

    it('should not allow unauthorized access', function () {
        $this->unauthorized_user();
        $post = \App\Models\Blog\BlogPost::factory()->create();
        $postData = [
            'title'        => 'New Blog Post',
            'slug'         => 'new-blog-post',
            'excerpt'      => 'This is a new blog post.',
            'body'         => 'Full content of the new blog post.',
            'status'       => PublicationStatusEnum::DRAFT->value,
            'published_at' => now()->toDateTimeString(),
            'author_id'    => null,
            'category_ids' => [],
            'is_featured'  => false,
            'media'        => [
                'cover' => [$this->cover->id],
            ],
        ];
        $response = $this->getJson(route('api.v1.admin.blog.post.index'));
        $response->assertForbidden();
        $response = $this->postJson(route('api.v1.admin.blog.post.store'), $postData);
        $response->assertForbidden();
        $response = $this->getJson(route('api.v1.admin.blog.post.show', ['post' => $post->id]));
        $response->assertForbidden();
        $response = $this->putJson(route('api.v1.admin.blog.post.update', ['post' => $post->id]), $postData);
        $response->assertForbidden();
        $response = $this->deleteJson(route('api.v1.admin.blog.post.destroy', ['post' => $post->id]));
        $response->assertForbidden();
    });
});
