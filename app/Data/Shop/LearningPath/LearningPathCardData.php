<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Models\LearningPath;
use Spatie\LaravelData\Data;

final class LearningPathCardData extends Data
{
    /**
     * @param  array<int, LearningPathMediaData>  $media
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $description,
        public array $media = [],
        public int $step_count = 0,
    ) {}

    public static function fromModel(LearningPath $learningPath): self
    {
        return self::factory()->withoutMagicalCreation()->from([
            'id'          => (int) $learningPath->getKey(),
            'title'       => $learningPath->title,
            'slug'        => $learningPath->slug,
            'description' => $learningPath->description,
            'media'       => LearningPathMediaData::forModel($learningPath),
            'step_count'  => (int) ($learningPath->steps_count ?? 0),
        ]);
    }
}
