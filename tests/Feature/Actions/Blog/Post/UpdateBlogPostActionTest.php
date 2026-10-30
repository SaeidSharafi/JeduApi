<?php

declare(strict_types=1);

use App\Actions\Admin\Blog\Post\UpdateBlogPostAction;
use App\Data\Admin\Blog\Post\BlogPostUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Models\Course;
use Illuminate\Support\Carbon;

mutates(UpdateBlogPostAction::class);

describe('UpdateBlogPostAction', function (): void {
    beforeEach(function (): void {
        $this->staff = App\Models\Staff::factory()->create();
        Storage::fake('public');

        $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('main1.jpg'))
            ->toDisk('public')
            ->upload();
        $this->media2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('main2.jpg'))
            ->toDisk('public')
            ->upload();

        $this->category1 = App\Models\Blog\BlogCategory::factory()->create(['name' => 'Category 1']);
        $this->category2 = App\Models\Blog\BlogCategory::factory()->create(['name' => 'Category 2']);

        $this->post = App\Models\Blog\BlogPost::factory()->create([
            'title'       => 'Original Title',
            'slug'        => 'original-title',
            'body'        => 'Original body content.',
            'excerpt'     => 'Original excerpt.',
            'author_id'   => $this->staff->id,
            'status'      => 'draft',
            'is_featured' => false,
        ]);
        $this->post->attachMedia($this->media, 'main');
        $this->post->categories()->attach($this->category1->id);

        $this->updateData = fn (string $status, ?Carbon $publishedAt = null): BlogPostUpdateData => new BlogPostUpdateData(
            title: 'Updated Title',
            slug: null,
            body: '<p>Updated body.</p>',
            excerpt: 'Updated excerpt.',
            status: $status,
            author_id: $this->staff->id,
            published_at: $publishedAt,
        );
    });

    it('updates a blog post', function (): void {
        $relatedProductable = Course::factory()->create();
        $data               = BlogPostUpdateData::from(
            [
                'title'                => 'Updated Title',
                'slug'                 => 'updated-title',
                'body'                 => str_repeat('This is the updated body content. ', 50), // 50 repetitions to increase word count
                'excerpt'              => 'Updated excerpt.',
                'status'               => 'published',
                'author_id'            => $this->staff->id,
                'published_at'         => now(),
                'is_featured'          => true,
                'main_productable'     => ['id' => $relatedProductable->id, 'type' => 'course'],
                'category_ids'         => [$this->category2->id],
                'related_productables' => [['id' => $relatedProductable->id, 'type' => 'course']],
                'media'                => [
                    'cover' => [$this->media2->id],
                ],
            ]
        );

        $action      = app(UpdateBlogPostAction::class);
        $updatedPost = $action->handle($this->post, $data);
        $updatedPost->loadRelatedproductables();
        expect($updatedPost)->toBeInstanceOf(App\Models\Blog\BlogPost::class)
            ->and($updatedPost->title)->toBe('Updated Title')
            ->and($updatedPost->slug)->toBe('updated-title')
            ->and($updatedPost->body)->toBe(str_repeat('This is the updated body content. ', 50))
            ->and($updatedPost->excerpt)->toBe('Updated excerpt.')
            ->and($updatedPost->author_id)->toBe($this->staff->id)
            ->and($updatedPost->status)->toBe(PublicationStatusEnum::PUBLISHED)
            ->and($updatedPost->is_featured)->toBeTrue()
            ->and($updatedPost->firstMedia('cover')->getUrl())->toBe($this->media2->getUrl())
            ->and($updatedPost->categories->pluck('id')->toArray())->toBe([$this->category2->id])
            ->and($updatedPost->main_productable_id)->toBe($relatedProductable->id)
            ->and($updatedPost->main_productable_type)->toBe(Course::class)
            ->and($updatedPost->related_productables->pluck('id')->toArray())->toBe([$relatedProductable->id])
            ->and($updatedPost->read_time_minutes)->toBe(2); // Assuming ~200 words per minute
    });

    it('updates a blog post and removing media', function (): void {
        $data = BlogPostUpdateData::from(
            [
                'title'                => 'Updated Title No Slug Change',
                'slug'                 => null,
                'body'                 => 'Updated body content without slug change.',
                'excerpt'              => 'Updated excerpt without slug change.',
                'status'               => 'published',
                'author_id'            => $this->staff->id,
                'published_at'         => verta()->format('Y-m-d H:i:s'),
                'is_featured'          => true,
                'main_productable'     => null,
                'category_ids'         => [$this->category2->id],
                'related_productables' => [],
                'media'                => [
                    'cover' => [$this->media->id],
                ],
            ]
        );

        $action      = app(UpdateBlogPostAction::class);
        $updatedPost = $action->handle($this->post, $data);
        $updatedPost->loadRelatedproductables();
        expect($updatedPost)->toBeInstanceOf(App\Models\Blog\BlogPost::class)
            ->and($updatedPost->title)->toBe('Updated Title No Slug Change')
            ->and($updatedPost->slug)->toBe('updated-title-no-slug-change')
            ->and($updatedPost->body)->toBe('Updated body content without slug change.')
            ->and($updatedPost->excerpt)->toBe('Updated excerpt without slug change.')
            ->and($updatedPost->author_id)->toBe($this->staff->id)
            ->and($updatedPost->status)->toBe(PublicationStatusEnum::PUBLISHED)
            ->and($updatedPost->is_featured)->toBeTrue()
            ->and($updatedPost->firstMedia('cover')->getUrl())->toBe($this->media->getUrl())
            ->and($updatedPost->firstMedia('video'))->toBeNull()
            ->and($updatedPost->categories->pluck('id')->toArray())->toBe([$this->category2->id])
            ->and($updatedPost->main_productable_id)->toBeNull()
            ->and($updatedPost->main_productable_type)->toBeNull()
            ->and($updatedPost->related_productables->pluck('id')->toArray())->toBe([])
            ->and($updatedPost->read_time_minutes)->toBe(1); // Assuming ~200 words per minute
    });

    it('measures reading time for a Persian body', function (): void {
        // 40 repetitions of a 10-word Persian sentence = 400 words.
        $longPersianBody = '<p>'.str_repeat('این یک متن آزمایشی فارسی برای سنجش زمان مطالعه است. ', 40).'</p>';

        $data = BlogPostUpdateData::from(
            [
                'title'        => 'Persian Blog Post',
                'slug'         => null,
                'body'         => $longPersianBody,
                'excerpt'      => 'خلاصه',
                'status'       => 'published',
                'author_id'    => $this->staff->id,
                'published_at' => now(),
                'is_featured'  => false,
                'category_ids' => [$this->category2->id],
                'media'        => ['cover' => [$this->media->id]],
            ]
        );

        $updatedPost = app(UpdateBlogPostAction::class)->handle($this->post, $data);

        expect($updatedPost->read_time_minutes)->toBe(2); // 400 words / 200 wpm = 2 minutes
    });

    it('keeps the one-minute floor for a short body', function (): void {
        $data = BlogPostUpdateData::from(
            [
                'title'        => 'Short Blog Post',
                'slug'         => null,
                'body'         => '<p><strong>سلام</strong> دنیا</p>',
                'excerpt'      => 'خلاصه',
                'status'       => 'published',
                'author_id'    => $this->staff->id,
                'published_at' => now(),
                'is_featured'  => false,
                'category_ids' => [$this->category2->id],
                'media'        => ['cover' => [$this->media->id]],
            ]
        );

        $updatedPost = app(UpdateBlogPostAction::class)->handle($this->post, $data);

        expect($updatedPost->read_time_minutes)->toBe(1);
    });

    it('counts words separated by entities', function (): void {
        // 399 Persian words glued by non-breaking-space entities count as one
        // token unless the entity is decoded first.
        $body = '<p>'.implode('&nbsp;', array_fill(0, 399, 'کلمه')).'</p>';

        $data = BlogPostUpdateData::from(
            [
                'title'        => 'Entity Heavy Blog Post',
                'slug'         => null,
                'body'         => $body,
                'excerpt'      => 'خلاصه',
                'status'       => 'published',
                'author_id'    => $this->staff->id,
                'published_at' => now(),
                'is_featured'  => false,
                'category_ids' => [$this->category2->id],
                'media'        => ['cover' => [$this->media->id]],
            ]
        );

        $updatedPost = app(UpdateBlogPostAction::class)->handle($this->post, $data);

        expect($updatedPost->read_time_minutes)->toBe(2); // 399 words / 200 wpm = 2 minutes
    });

    it('keeps the one-minute floor for a body of entities only', function (): void {
        $data = BlogPostUpdateData::from(
            [
                'title'        => 'Whitespace Blog Post',
                'slug'         => null,
                'body'         => '<p>'.str_repeat('&nbsp;', 50).'</p>',
                'excerpt'      => 'خلاصه',
                'status'       => 'published',
                'author_id'    => $this->staff->id,
                'published_at' => now(),
                'is_featured'  => false,
                'category_ids' => [$this->category2->id],
                'media'        => ['cover' => [$this->media->id]],
            ]
        );

        $updatedPost = app(UpdateBlogPostAction::class)->handle($this->post, $data);

        expect($updatedPost->read_time_minutes)->toBe(1);
    });

    it('sets published_at when a non-published post becomes published', function (): void {
        $this->travelTo('2025-06-01 12:00:00');
        $this->post->update([
            'status'       => PublicationStatusEnum::DRAFT,
            'published_at' => now()->subMonth(),
        ]);

        $updatedPost = app(UpdateBlogPostAction::class)->handle(
            $this->post,
            ($this->updateData)(PublicationStatusEnum::PUBLISHED->value),
        );

        expect($updatedPost->published_at)->not->toBeNull()
            ->and($updatedPost->published_at->equalTo(now()))->toBeTrue();
    });

    it('keeps the original published_at when an already published post is edited', function (): void {
        $this->travelTo('2025-06-01 12:00:00');
        $originalPublishedAt = Carbon::parse('2025-05-25 12:00:00');
        $this->post->update([
            'status'       => PublicationStatusEnum::PUBLISHED,
            'published_at' => $originalPublishedAt,
        ]);

        $updatedPost = app(UpdateBlogPostAction::class)->handle(
            $this->post,
            ($this->updateData)(PublicationStatusEnum::PUBLISHED->value),
        );

        expect($updatedPost->published_at->equalTo($originalPublishedAt))->toBeTrue();
    });

    it('clears published_at when the post leaves the published status', function (string $status): void {
        $this->post->update([
            'status'       => PublicationStatusEnum::PUBLISHED,
            'published_at' => now()->subWeek(),
        ]);

        $updatedPost = app(UpdateBlogPostAction::class)->handle(
            $this->post,
            ($this->updateData)($status),
        );

        expect($updatedPost->published_at)->toBeNull();
    })->with([
        'draft'     => PublicationStatusEnum::DRAFT->value,
        'archived'  => PublicationStatusEnum::ARCHIVED->value,
        'scheduled' => PublicationStatusEnum::SCHEDULED->value,
    ]);

    it('keeps an explicitly provided published_at on update', function (): void {
        $this->travelTo('2025-06-01 12:00:00');
        $explicitPublishedAt = Carbon::parse('2025-05-30 12:00:00');
        $this->post->update([
            'status'       => PublicationStatusEnum::DRAFT,
            'published_at' => now()->subMonth(),
        ]);

        $updatedPost = app(UpdateBlogPostAction::class)->handle(
            $this->post,
            ($this->updateData)(PublicationStatusEnum::PUBLISHED->value, $explicitPublishedAt),
        );

        expect($updatedPost->published_at->equalTo($explicitPublishedAt))->toBeTrue();
    });
});
