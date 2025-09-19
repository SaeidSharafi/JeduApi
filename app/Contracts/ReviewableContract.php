<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface ReviewableContract
{
    public function products(): MorphMany;

    public function getAllMedia(): ?array;

    public function scopeWithProductableMedia(Builder $query): Builder;

    public function scopeWithProductableCategories(Builder $query): Builder;

    public function scopeWithProductableAssets(Builder $query): Builder;
    // public function scopeWithProductableAuditor(Builder $query): Builder;

    public function loadMediaWitVariant(): void;

    public function loadProductableCategories(): void;
}
