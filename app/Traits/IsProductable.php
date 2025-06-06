<?php

namespace App\Traits;

use App\Data\MediaData;
use App\Data\PrivateFileData;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Plank\Mediable\Media;

trait IsProductable
{
    public function products(): MorphMany
    {
        return $this->morphMany(Product::class, 'productable');
    }

    public function scopeWithProductableMedia(Builder $query,array $tags = []): Builder
    {
        $query->withMediaAndVariantsMatchAll($tags);

        return $query;
    }

    public function scopeWithProductableCategories(Builder $query): Builder
    {
        $query->with('categories');

        return $query;
    }

    public function scopeWithProductableAssets(Builder $query): Builder
    {
        if (method_exists($this, 'digitalAssets')) {
            $query->with('digitalAssets');
        }

        return $query;
    }
    public function getProductableMedia(): array
    {

        if ($this->relationLoaded('media')) {
            $media = [];
            foreach (['gallery', 'video', 'cover', 'certificate'] as $tag) {
                $media[$tag] = $this->getMedia($tag)
                    ->map(fn(Media $m): MediaData => MediaData::fromModel($m, $tag))
                    ->toArray();
            }
            return $media;
        }
        return [];
    }
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


    //public function scopeWithProductableAuditor(Builder $query): Builder
    //{
    //    $query->with('auditor');
    //
    //    return $query;
    //}

    public function loadProductableMedia(array $tags = []): void
    {
        $this->loadMediaAndVariantsMatchAll($tags);
    }

    public function loadProductableCategories(): void
    {
        if (!$this->relationLoaded('categories')) {
            $this->load('categories');
        }
    }
}
