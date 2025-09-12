<?php

namespace App\Actions\Admin\Review;

use App\Data\Admin\Review\FeaturedStatusData;
use App\Models\Review;

class UpdateReviewFeaturedStatusAction
{
    public function handle(Review $review, FeaturedStatusData $featuredStatusData): void
    {
        if ($featuredStatusData->is_featured !== null) {
            $review->update(['is_featured' => $featuredStatusData->is_featured]);
            return;
        }
        $review->update(['is_featured' => !$review->is_featured]);
    }
}
