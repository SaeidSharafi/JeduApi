<?php

namespace App\Listeners;

use App\Enums\MorphTypeEnum;
use App\Enums\ReviewStatusEnum;
use App\Events\ReviewableAggregatesChanged;
use App\Models\Review;
use App\Traits\HasReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RecalculateReviewableAggregates implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ReviewableAggregatesChanged $event): void
    {
        $reviewableModel = MorphTypeEnum::from($event->reviewableType)->getModelClass();
        $reviewable = $reviewableModel::find($event->reviewableId);

        if (!$reviewable || ! in_array(HasReview::class, class_uses($reviewable))) {
            return;
        }

        $stats = Review::where('reviewable_type', $event->reviewableType)
            ->where('reviewable_id', $reviewable->id)
            ->where('status', ReviewStatusEnum::APPROVED)
            ->selectRaw('count(*) as count, avg(rating) as avg_rating')
            ->first();

        $reviewable->updateQuietly([
            'review_count' => $stats->count ?? 0,
            'average_rating' => $stats->avg_rating ?? 0.00,
        ]);
    }
}
