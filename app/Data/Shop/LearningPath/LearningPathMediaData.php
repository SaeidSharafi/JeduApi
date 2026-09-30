<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Media;
use Spatie\LaravelData\Data;

final class LearningPathMediaData extends Data
{
    public function __construct(
        public string $url,
    ) {}

    public static function fromModel(Media $media): self
    {
        return self::factory()->withoutMagicalCreation()->from([
            'url' => $media->getUrl(),
        ]);
    }

    /**
     * @return array<int, self>
     */
    public static function forModel(Model $model): array
    {
        if (! $model->relationLoaded('media')) {
            $model->load('media');
        }

        /** @var Collection<int, Media> $media */
        $media = $model->getRelation('media');

        return $media
            ->unique('id')
            ->map(static fn (Media $item): self => self::fromModel($item))
            ->values()
            ->all();
    }
}
