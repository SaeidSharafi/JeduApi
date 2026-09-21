<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Cache\CacheStore;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\System\CacheTag;
use App\Models\Blog\BlogPost;
use Illuminate\Console\Command;

final class PublishPostCommand extends Command
{
    protected $signature = 'post:publish';

    protected $description = 'publish posts that has publish_at in the past and status is draft';

    public function __construct(private readonly CacheStore $cache)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('Starting post publishing process...');

        $postsToPublish = BlogPost::query()
            ->where('status', PublicationStatusEnum::SCHEDULED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $count = $postsToPublish->count();

        if ($count === 0) {
            $this->info('No posts are scheduled for publication at this time.');

            return;
        }

        $updatedCount = $postsToPublish->update(['status' => PublicationStatusEnum::PUBLISHED]);

        // The mass update bypasses the model events and blog post actions, so the
        // search results cached for the old status are cleared here explicitly.
        $this->cache->invalidate(CacheTag::Search);

        $this->info("Successfully published {$updatedCount} post(s).");
    }
}
