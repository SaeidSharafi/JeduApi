<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;

covers(App\Console\Commands\PublishPostCommand::class);

describe('PublishPostCommand', function (): void {
    beforeEach(function (): void {
        $this->command = app(App\Console\Commands\PublishPostCommand::class);
    });

    it('should publish scheduled posts with past publish_at dates', function (): void {
        // Create a scheduled post with a past publish_at date
        $post = App\Models\Blog\BlogPost::factory()->create([
            'status'       => PublicationStatusEnum::SCHEDULED,
            'published_at' => now()->subDay(),
        ]);

        // Run the command
        $this->artisan('post:publish')
            ->expectsOutput('Starting post publishing process...')
            ->expectsOutput('Successfully published 1 post(s).')
            ->assertExitCode(0);

        // Refresh the post instance to get the latest data from the database
        $post->refresh();

        // Assert that the post status has been updated to PUBLISHED
        expect($post->status)->toBe(PublicationStatusEnum::PUBLISHED);
    });

    it('should not publish posts with future publish_at dates', function (): void {
        // Create a scheduled post with a future publish_at date
        $post = App\Models\Blog\BlogPost::factory()->create([
            'status'       => PublicationStatusEnum::SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        // Run the command
        $this->artisan('post:publish')
            ->expectsOutput('Starting post publishing process...')
            ->expectsOutput('No posts are scheduled for publication at this time.')
            ->assertExitCode(0);

        // Refresh the post instance to get the latest data from the database
        $post->refresh();

        // Assert that the post status is still SCHEDULED
        expect($post->status)->toBe(PublicationStatusEnum::SCHEDULED);
    });

    it('should not publish posts that are not scheduled', function (): void {
        // Create a draft post
        $draftPost = App\Models\Blog\BlogPost::factory()->create([
            'status'       => PublicationStatusEnum::DRAFT,
            'published_at' => now()->subDay(),
        ]);

        // Create a published post
        $publishedPost = App\Models\Blog\BlogPost::factory()->create([
            'status'       => PublicationStatusEnum::PUBLISHED,
            'published_at' => now()->subDays(2),
        ]);

        // Run the command
        $this->artisan('post:publish')
            ->expectsOutput('Starting post publishing process...')
            ->expectsOutput('No posts are scheduled for publication at this time.')
            ->assertExitCode(0);

        // Refresh the post instances to get the latest data from the database
        $draftPost->refresh();
        $publishedPost->refresh();

        // Assert that the draft post status is still DRAFT
        expect($draftPost->status)->toBe(PublicationStatusEnum::DRAFT);
        // Assert that the published post status is still PUBLISHED
        expect($publishedPost->status)->toBe(PublicationStatusEnum::PUBLISHED);

    });

    it('clears cached search results when posts are published', function (): void {
        App\Models\Blog\BlogPost::factory()->create([
            'status'       => PublicationStatusEnum::SCHEDULED,
            'published_at' => now()->subDay(),
        ]);

        $cache = app(App\Contracts\Cache\CacheStore::class);
        $cache->put(App\Enums\System\CacheKey::Search, ['hash' => 'query-hash'], ['stale']);
        $cache->put(App\Enums\System\CacheKey::SearchSuggest, ['hash' => 'suggest-hash'], ['stale']);

        $this->artisan('post:publish')->assertExitCode(0);

        expect($cache->get(App\Enums\System\CacheKey::Search, ['hash' => 'query-hash']))->toBeNull()
            ->and($cache->get(App\Enums\System\CacheKey::SearchSuggest, ['hash' => 'suggest-hash']))->toBeNull();
    });

    it('leaves cached search results alone when nothing is published', function (): void {
        App\Models\Blog\BlogPost::factory()->create([
            'status'       => PublicationStatusEnum::SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $cache = app(App\Contracts\Cache\CacheStore::class);
        $cache->put(App\Enums\System\CacheKey::Search, ['hash' => 'query-hash'], ['stale']);

        $this->artisan('post:publish')->assertExitCode(0);

        expect($cache->get(App\Enums\System\CacheKey::Search, ['hash' => 'query-hash']))->toBe(['stale']);
    });
});
