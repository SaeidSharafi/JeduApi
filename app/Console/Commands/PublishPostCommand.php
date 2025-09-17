<?php

namespace App\Console\Commands;

use App\Enums\PublicationStatusEnum;
use App\Models\Blog\BlogPost;
use Illuminate\Console\Command;

class PublishPostCommand extends Command
{
    protected $signature = 'post:publish';

    protected $description = 'publish posts that has publish_at in the past and status is draft';


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

        $this->info("Successfully published {$updatedCount} post(s).");
    }
}
