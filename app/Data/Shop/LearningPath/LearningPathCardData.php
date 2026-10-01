<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Models\LearningPath;
use Spatie\LaravelData\Data;

final class LearningPathCardData extends Data
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $description,
        public ?string $thumbnail_url = null,
        public int $step_count = 0,
    ) {}

    public static function fromModel(LearningPath $learningPath): self
    {
        return new self(
            title     : $learningPath->title,
            slug      : $learningPath->slug,
            description: $learningPath->description,
            thumbnail_url: $learningPath->thumbnail_url,
            step_count: (int) ($learningPath->steps_count ?? 0),
        );
    }
}
