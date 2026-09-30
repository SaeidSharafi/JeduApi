<?php

declare(strict_types=1);

namespace App\Data\Admin\LearningPath;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class LearningPathProductableData extends Data
{
    public function __construct(
        public string $type,
        public int $id,
        public string $name,
        public ?string $slug,
        public ?string $thumbnail_url,
    ) {}

    public static function fromModel(Model $productable, string $type): self
    {
        return self::factory()->withoutMagicalCreation()->from([
            'type'          => $type,
            'id'            => (int) $productable->getKey(),
            'name'          => (string) ($productable->getAttribute('full_name') ?? $productable->getAttribute('name')),
            'slug'          => $productable->getAttribute('slug'),
            'thumbnail_url' => $productable->getAttribute('thumbnail_url'),
        ]);
    }
}
