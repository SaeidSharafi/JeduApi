<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasReview
{
    public function reviews(): MorphMany
    {
        return $this->morphMany(\App\Models\Review::class, 'reviewable');
    }

    protected function initializeHasReview(): void
    {
        $this->fillable[]    = 'review_count';
        $this->fillable[]    = 'average_rating';
    }
}
