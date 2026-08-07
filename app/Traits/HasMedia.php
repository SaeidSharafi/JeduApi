<?php

declare(strict_types=1);

namespace App\Traits;

use App\Data\Admin\MediaData;
use App\Enums\MediaTagEnum;
use Illuminate\Database\Eloquent\Builder;
use Plank\Mediable\Media;

trait HasMedia
{
    /** @var array<int, MediaTagEnum|string> */
    public array $exceptTags = [];

    /**
     * @param  Builder<$this>  $query
     * @param  array<int, string>  $tags
     * @return Builder<$this>
     */
    /**
     * @param  Builder<self>  $query
     * @param  array<int, string>  $tags
     * @return Builder<self>
     */
    public function scopeWithProductableMedia(Builder $query, array $tags = []): Builder
    {
        $query->withMediaAndVariantsMatchAll($tags);

        return $query;
    }

    /**
     * @param  array<int, MediaTagEnum|string>  $onlyTags
     * @return array<string, mixed>
     */
    public function getAllMedia(bool $urlOnly = false, array $onlyTags = []): array
    {
        $tags = $onlyTags ?: array_diff(
            array_map(static fn (MediaTagEnum $tag): string => $tag->value, MediaTagEnum::cases()),
            array_map(static fn (MediaTagEnum|string $tag): string => $tag instanceof MediaTagEnum ? $tag->value : $tag, $this->exceptTags),
        );
        if ($this->relationLoaded('media')) {
            $media = [];
            foreach ($tags as $tag) {
                $tagValue         = $tag instanceof MediaTagEnum ? $tag->value : $tag;
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

    /**
     * @return array<int, MediaData>|MediaData|null
     */
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

    /**
     * @param  array<int, string>  $tags
     */
    public function loadMediaWitVariant(array $tags = []): void
    {
        $this->loadMediaWithVariantsMatchAll($tags);
    }
}
