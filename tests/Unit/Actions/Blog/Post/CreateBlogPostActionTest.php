<?php

use App\Actions\Admin\Blog\Post\CreateBlogPostAction;
use App\Data\Admin\Blog\Post\BlogPostCreateData;
use App\Enums\ProductableEnum;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Course;
use App\Models\Product;
use App\Models\Seminar;

describe('CreateBlogPostAction', function () {
    beforeEach(function () {
        $this->staff = \App\Models\Staff::factory()->create();

        Storage::fake('public');

        $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('main1.jpg'))
            ->toDisk('public')
            ->upload();
        $this->media2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('main2.jpg'))
            ->toDisk('public')
            ->upload();

        $this->category1 = BlogCategory::factory()->create([
            'name' => 'Category 1',
            'slug' => 'category-1',
        ]);
        $this->category2 = BlogCategory::factory()->create([
            'name' => 'Category 2',
            'slug' => 'category-2',
        ]);

        $this->productable1 = Course::factory()->create();
        $this->productable2 = Seminar::factory()->create();
    });

    it('creates a blog post with all fields', function () {
        $data = new BlogPostCreateData(
            title: 'New Blog Post',
            slug: 'new-blog-post',
            body: '<p>This is the body of the blog post. It has some content to read.</p>',
            excerpt: 'This is a short excerpt.',
            status: 'published',
            author_id: $this->staff->id,
            published_at: now(),
            is_featured: true,
            main_productable: ['type' => 'course', 'id' => $this->productable1->id],
            category_ids: [$this->category1->id, $this->category2->id],
            related_productables: [
                ['type' => 'seminar', 'id' => $this->productable2->id],
            ],
            media: [
                'cover' => [$this->media->id],
            ],
        );

        $action = new CreateBlogPostAction();
        $post = $action->handle($data);
        $post->loadRelatedproductables();
        expect($post)->toBeInstanceOf(BlogPost::class)
            ->and($post->title)->toBe('New Blog Post')
            ->and($post->slug)->toBe('new-blog-post')
            ->and($post->body)->toBe('<p>This is the body of the blog post. It has some content to read.</p>')
            ->and($post->excerpt)->toBe('This is a short excerpt.')
            ->and($post->author_id)->toBe($this->staff->id)
            ->and($post->status)->toBe(\App\Enums\PublicationStatusEnum::PUBLISHED)
            ->and($post->is_featured)->toBeTrue()
            ->and($post->read_time_minutes)->toBe(1) // Assuming ~200 words per minute
            ->and($post->firstMedia('cover')->getUrl())->toBe($this->media->getUrl())
            ->and($post->categories->pluck('id')->toArray())->toEqualCanonicalizing([
                $this->category1->id, $this->category2->id
            ])
            ->and($post->main_productable_id)->toBe($this->productable1->id)
            ->and($post->main_productable_type)->toBe(Course::class)
            ->and($post->relatedProductables->pluck('id')->toArray())->toEqualCanonicalizing([$this->productable2->id]);
    });

    it('creates a blog post with minimal fields', function () {
        $data = new BlogPostCreateData(
            title: 'Minimal Blog Post',
            slug: null,
            body: '<p>Short body.</p>',
            excerpt: 'Excerpt',
            status: 'draft',
            author_id: $this->staff->id,
            published_at: null,
            is_featured: false,
            main_productable: null,
            category_ids: [],
            related_productables: [],
            media: [
                'cover' => [$this->media->id],
            ],
        );

        $action = new CreateBlogPostAction();
        $post = $action->handle($data);

        expect($post)->toBeInstanceOf(BlogPost::class)
            ->and($post->title)->toBe('Minimal Blog Post')
            ->and($post->slug)->toBe('minimal-blog-post') // Slug auto-generated
            ->and($post->body)->toBe('<p>Short body.</p>')
            ->and($post->excerpt)->toBe('Excerpt')
            ->and($post->author_id)->toBe($this->staff->id)
            ->and($post->status)->toBe(\App\Enums\PublicationStatusEnum::DRAFT)
            ->and($post->is_featured)->toBeFalse()
            ->and($post->read_time_minutes)->toBe(1) // Minimum read time
            ->and($post->firstMedia('main'))->toBeNull()
            ->and($post->categories)->toBeEmpty()
            ->and($post->main_productable_id)->toBeNull()
            ->and($post->main_productable_type)->toBeNull()
            ->and($post->relatedProductables)->toBeEmpty();
    });

    it('creates a blog post and calculates read time correctly', function (){
        $longBody = '<p>' . str_repeat('This is a test sentence. ', 100) . '</p>'; // ~500 words
        $data = new BlogPostCreateData(
            title: 'Long Read Blog Post',
            slug: null,
            body: $longBody,
            excerpt: 'Long read excerpt',
            status: 'published',
            author_id: $this->staff->id,
            published_at: now(),
            is_featured: false,
            main_productable: null,
            category_ids: [],
            related_productables: [],
            media: [
                'cover' => [$this->media->id],
            ],
        );

        $action = new CreateBlogPostAction();
        $post = $action->handle($data);

        expect($post)->toBeInstanceOf(BlogPost::class)
            ->and($post->read_time_minutes)->toBe(3); // 500 words / 200 wpm = 2.5, rounded up to 3
    });
});
