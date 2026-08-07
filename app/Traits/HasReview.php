<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReview
{
    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    protected function initializeHasReview(): void
    {
        $this->fillable[] = 'review_count';
        $this->fillable[] = 'average_rating';
    }
}
