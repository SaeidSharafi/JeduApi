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

    public function getAllMedia(bool $urlOnly = false): array
    {
        $tags = array_diff(MediaTagEnum::cases(), $this->exceptTags);

        if ($this->relationLoaded('media')) {
            $media = [];
            foreach ($tags as $tag) {
                $media[$tag->value] = $this->getMedia($tag)
                    ->map(fn (Media $m): MediaData => MediaData::fromModel($m, $tag->value))
                    ->when($urlOnly, fn ($q) => $q->pluck('url'))
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
