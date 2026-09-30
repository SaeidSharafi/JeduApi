<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class LearningPathProductableData extends Data
{
    /**
     * @param  array<int, LearningPathMediaData>  $media
     */
    public function __construct(
        public int $id,
        public string $productable_type,
        public string $name,
        public ?string $short_description,
        public ?string $excerpt,
        public ?string $slug,
        public ?string $thumbnail_url,
        public array $media = [],
    ) {}

    public static function fromModel(Model $productable, string $type): self
    {
        $name    = $productable->getAttribute('full_name') ?: $productable->getAttribute('name');
        $excerpt = $productable->getAttribute('description');

        return self::factory()->withoutMagicalCreation()->from([
            'id'                => (int) $productable->getKey(),
            'productable_type'  => $type,
            'name'              => (string) $name,
            'short_description' => $excerpt,
            'excerpt'           => $excerpt,
            'slug'              => $productable->getAttribute('slug'),
            'thumbnail_url'     => $productable->getAttribute('thumbnail_url'),
            'media'             => LearningPathMediaData::forModel($productable),
        ]);
    }
}
