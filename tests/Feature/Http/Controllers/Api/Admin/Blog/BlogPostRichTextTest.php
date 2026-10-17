<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Blog\BlogPost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Plank\Mediable\Facades\MediaUploader;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('Blog post rich text', function (): void {
    beforeEach(function (): void {
        $this->authorized_user([PermissionEnum::BLOG_POST_CREATE, PermissionEnum::BLOG_POST_UPDATE]);

        Storage::fake('public');
        $this->cover = MediaUploader::fromSource(UploadedFile::fake()->image('cover.jpg'))
            ->toDisk('public')
            ->upload();

        // A Word/CKEditor paste: a heading that must be downgraded, pasted
        // typography and colour, a highlight, a scripted link, executable
        // markup and an HTML-embed subtree.
        $this->dirtyBody = '<h1 id="title" onclick="alert(1)">عنوان</h1>'
            .'<p class="lead" style="font-family: Arial; color: red; text-align: center">متن <mark>مهم</mark> '
            .'<a href="javascript:alert(1)" target="_blank">لینک</a></p>'
            .'<script>alert(1)</script>'
            .'<div class="raw-html-embed"><p>arbitrary</p></div>';

        $this->cleanBody = '<h2>عنوان</h2>'
            .'<p style="text-align: center">متن <mark>مهم</mark> '
            .'<a target="_blank" rel="noopener noreferrer">لینک</a></p>';
    });

    it('stores and serves the sanitized body when a post is created', function (): void {
        $response = $this->postJson(route('api.v1.admin.blog.posts.store'), [
            'title'            => 'Rich text post',
            'slug'             => 'rich-text-post',
            'excerpt'          => 'Excerpt of the post.',
            'body'             => $this->dirtyBody,
            'status'           => PublicationStatusEnum::DRAFT->value,
            'author_id'        => null,
            'is_featured'      => false,
            'meta_title'       => Str::random(70),
            'meta_description' => Str::random(100),
            'meta_keywords'    => Str::random(10).','.Str::random(10),
            'media'            => ['cover' => [$this->cover->id]],
        ]);

        $response->assertCreated();
        expect($response->json('data.body'))->toBe($this->cleanBody);

        $this->assertDatabaseHas('blog_posts', [
            'slug' => 'rich-text-post',
            'body' => $this->cleanBody,
        ]);
    });

    it('stores and serves the sanitized body when a post is updated', function (): void {
        $post = BlogPost::factory()->create(['body' => '<p>old body</p>']);

        $response = $this->putJson(route('api.v1.admin.blog.posts.update', ['post' => $post->id]), [
            'title'            => 'Updated rich text post',
            'body'             => $this->dirtyBody,
            'excerpt'          => 'Excerpt of the post.',
            'status'           => PublicationStatusEnum::PUBLISHED->value,
            'media'            => ['cover' => [$this->cover->id]],
            'meta_title'       => Str::random(70),
            'meta_description' => Str::random(100),
            'meta_keywords'    => Str::random(10).','.Str::random(10),
        ]);

        $response->assertOk();
        expect($response->json('data.body'))->toBe($this->cleanBody);

        $this->assertDatabaseHas('blog_posts', [
            'id'   => $post->id,
            'body' => $this->cleanBody,
        ]);
    });
});
