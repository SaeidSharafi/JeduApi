<?php

declare(strict_types=1);

namespace App\Actions\Admin\Setting\StudentStory;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Models\StudentStory;
use Illuminate\Support\Facades\DB;

final class DeleteStudentStoryAction
{
    public function __construct(private readonly CacheStore $cache) {}

    public function handle(StudentStory $story): void
    {
        DB::transaction(function () use ($story): void {
            $story->media()->delete();
            $story->delete();
        });

        $this->cache->invalidate(CacheTag::HomePage);
        StorefrontCacheInvalidated::dispatch(StorefrontTag::HomeStudentStories);
    }
}
