<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Cache\CacheStore;
use App\Enums\Content\ReviewStatusEnum;
use App\Enums\System\CacheTag;
use App\Enums\System\MorphTypeEnum;
use App\Events\ReviewableAggregatesChanged;
use App\Models\Review;
use App\Traits\HasReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RecalculateReviewableAggregates implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly CacheStore $cache) {}

    /**
     * Handle the event.
     */
    public function handle(ReviewableAggregatesChanged $event): void
    {
        $reviewableModel = MorphTypeEnum::from($event->reviewableType)->getModelClass();
        $reviewable      = $reviewableModel::find($event->reviewableId);

        if (! $reviewable || ! in_array(HasReview::class, class_uses($reviewable))) {
            return;
        }

        $stats = Review::where('reviewable_type', $event->reviewableType)
            ->where('reviewable_id', $reviewable->id)
            ->where('status', ReviewStatusEnum::APPROVED)
            ->selectRaw('count(*) as count, avg(rating) as avg_rating')
            ->first();

        $reviewable->updateQuietly([
            'review_count'   => $stats->count      ?? 0,
            'average_rating' => $stats->avg_rating ?? 0.00,
        ]);

        // Search result pages cache the review aggregates of the products they return.
        $this->cache->invalidate(CacheTag::Search);
    }
}
