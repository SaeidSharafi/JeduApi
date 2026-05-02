<?php

declare(strict_types=1);

namespace App\Traits;

use App\Data\Admin\MediaData;
use App\Enums\MediaTagEnum;
use Illuminate\Database\Eloquent\Builder;
use Plank\Mediable\Media;

trait HasMedia
{
    public array $exceptTags = [];

    public function scopeWithProductableMedia(Builder $query, array $tags = []): Builder
    {
        $query->withMediaAndVariantsMatchAll($tags);

        return $query;
    }

    public function getAllMedia(bool $urlOnly = false, array $onlyTags=[]): array
    {
        $tags = $onlyTags ?: array_diff(MediaTagEnum::cases(), $this->exceptTags);
        if ($this->relationLoaded('media')) {
            $media = [];
            foreach ($tags as $tag) {
                $tagValue = $tag instanceof MediaTagEnum ? $tag->value : $tag;
                $media[$tagValue] = $this->getMedia($tagValue)
                    ->map(fn (Media $m): MediaData => MediaData::fromModel($m, $tagValue))
                    ->when(
                        $urlOnly,
                        static fn ($items) => $items->map(static fn (MediaData $d) => $d->url)
                    )
                    ->toArray();
            }

            return $media;
        }

        return [];
    }

    public function getCoverMedia(bool $first = false): null|MediaData|array
    {
        if (! $this->relationLoaded('media')) {
            return [];
        }
        if ($first) {
            return $this->getMedia(MediaTagEnum::COVER->value)
                ->map(fn (Media $m): MediaData => MediaData::fromModel($m, MediaTagEnum::COVER->value))
                ->first();
        }

        return $this->getMedia(MediaTagEnum::COVER->value)
            ->map(fn (Media $m): MediaData => MediaData::fromModel($m, MediaTagEnum::COVER->value))
            ->toArray();
    }

    public function loadMediaWitVariant(array $tags = []): void
    {
        if (method_exists($this, 'loadMediaWithVariantsMatchAll')) {
            $this->loadMediaWithVariantsMatchAll($tags);
        }
    }
}
