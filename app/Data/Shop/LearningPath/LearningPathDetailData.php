<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use Spatie\LaravelData\Data;

final class LearningPathDetailData extends Data
{
    /**
     * @param  array<int, LearningPathStepData>  $steps
     * @param  array<int, LearningPathMediaData>  $media
     */
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
        return self::factory()->withoutMagicalCreation()->from([
            'id'                       => (int) $learningPath->getKey(),
            'title'                    => $learningPath->title,
            'slug'                     => $learningPath->slug,
            'description'              => $learningPath->description,
            'introduction_title'       => $learningPath->introduction_title,
            'introduction_description' => $learningPath->introduction_description,
            'conclusion_title'         => $learningPath->conclusion_title,
            'conclusion_description'   => $learningPath->conclusion_description,
            'meta_title'               => $learningPath->meta_title,
            'meta_description'         => $learningPath->meta_description,
            'meta_keywords'            => $learningPath->meta_keywords,
            'display_order'            => (int) $learningPath->display_order,
            'status'                   => $learningPath->status,
            'steps'                    => $learningPath->steps
                ->map(static fn (LearningPathStep $step): LearningPathStepData => LearningPathStepData::fromModel($step))
                ->values()
                ->all(),
            'media' => LearningPathMediaData::forModel($learningPath),
        ]);
    }
}
