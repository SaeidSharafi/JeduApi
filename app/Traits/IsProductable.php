<?php

declare(strict_types=1);

namespace App\Traits;

use App\Data\Admin\PrivateFileData;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Plank\Mediable\Media;

trait IsProductable
{
    /**
     * @return MorphMany<Product, $this>
     */
    public function products(): MorphMany
    {
        return $this->morphMany(Product::class, 'productable');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithProductableCategories(Builder $query): Builder
    {
        $query->with('categories');

        return $query;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithProductableAssets(Builder $query): Builder
    {
        if (method_exists($this, 'digitalAssets')) {
            $query->with('digitalAssets');
        }

        return $query;
    }

    /**
     * @return array<string, PrivateFileData|null>
     */
    public function getProductableAttachment(): array
    {
        if ($this->relationLoaded('media')) {
            $attachments = [];
            foreach (['main', 'preview'] as $tag) {
                $attachments[$tag] = $this->getMedia($tag)
                    ->map(fn (Media $m): PrivateFileData => PrivateFileData::fromModel($m, $tag))
                    ->toArray();
            }

            return $attachments;
        }

        return [];
    }

    // public function scopeWithProductableAuditor(Builder $query): Builder
    // {
    //    $query->with('auditor');
    //
    //    return $query;
    // }

    public function loadProductableCategories(): void
    {
        if (! $this->relationLoaded('categories')) {
            $this->load('categories');
        }
    }
}
