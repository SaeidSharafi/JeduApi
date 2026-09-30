<?php

declare(strict_types=1);

namespace App\Data\Admin\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use Spatie\LaravelData\Data;

final class LearningPathData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $description,
        public string $introduction_title,
        public string $introduction_description,
        public string $conclusion_title,
        public string $conclusion_description,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public int $display_order,
        public PublicationStatusEnum $status,
        public array $steps = [],
        public array $media = [],
    ) {}

    public static function fromModel(LearningPath $learningPath): self
    {
        $learningPath->loadMissing(['steps.productable']);
        if (! $learningPath->relationLoaded('media')) {
            $learningPath->loadMediaWithVariantsMatchAll();
        }

        return self::factory()->withoutMagicalCreation()->from([
            ...$learningPath->toArray(),
            'steps' => $learningPath->steps
                ->map(static fn (LearningPathStep $step): LearningPathStepData => LearningPathStepData::fromModel($step))
                ->all(),
            'media' => $learningPath->getAllMedia(),
        ]);
    }
}
