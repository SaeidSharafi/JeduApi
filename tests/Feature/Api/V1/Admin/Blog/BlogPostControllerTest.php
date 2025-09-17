<?php

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
});

describe('BlogPostController CRUD', function () {
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
        ];
        $response = $this->postJson(route('api.v1.admin.blog.post.store'), $postData);
        $response->assertCreated();
        $response->assertJsonFragment(['title' => 'New Blog Post']);
        $this->assertDatabaseHas('blog_posts', ['slug' => 'new-blog-post']);
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
            'title'        => 'Updated Blog Post Title',
            'excerpt'      => 'Updated excerpt.',
            'body'      => 'Updated full content.',
            'status' => PublicationStatusEnum::PUBLISHED->value,

        ];
        $response = $this->putJson(route('api.v1.admin.blog.post.update', ['post' => $post->id]), $updateData);
        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Updated Blog Post Title']);
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
        ];
        $response = $this->getJson(route('api.v1.admin.blog.post.index'));
        $response->assertForbidden();
        $response = $this->postJson(route('api.v1.admin.blog.post.store'), $postData);
        $response->assertForbidden();
        $response = $this->getJson(route('api.v1.admin.blog.post.show', ['post' => $post->id]));
        $response->assertForbidden();
        $response = $this->putJson(route('api.v1.admin.blog.post.update', ['post' => $post->id]),$postData);
        $response->assertForbidden();
        $response = $this->deleteJson(route('api.v1.admin.blog.post.destroy', ['post' => $post->id]));
        $response->assertForbidden();
    });
});
