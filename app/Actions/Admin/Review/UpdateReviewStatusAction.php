<?php

namespace App\Actions\Admin\Review;

use App\Enums\ReviewStatusEnum;
use App\Models\Review;

class UpdateReviewStatusAction
{
    public function handle(Review $review, ReviewStatusEnum $statusEnum): void
    {
        if ($review->status === $statusEnum) {
            return;
        }
        $review->update(['status' => $statusEnum]);
    }
}
