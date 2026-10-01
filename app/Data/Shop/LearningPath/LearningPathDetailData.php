<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\MediaTagEnum;
use App\Models\LearningPath;
use App\Services\LearningPath\ResolvedLearningPathStep;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class LearningPathDetailData extends Data
{
    /**
     * @param  array<int, LearningPathStepData>  $steps
     * @param  array<string, mixed>  $media
     */
    public function __construct(
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

    /**
     * @param  Collection<int, ResolvedLearningPathStep>  $resolvedSteps
     */
    public static function fromModel(LearningPath $learningPath, Collection $resolvedSteps): self
    {
        return new self(
            title: $learningPath->title,
            slug: $learningPath->slug,
            description: $learningPath->description,
            introduction_title: $learningPath->introduction_title,
            introduction_description: $learningPath->introduction_description,
            conclusion_title: $learningPath->conclusion_title,
            conclusion_description: $learningPath->conclusion_description,
            meta_title: $learningPath->meta_title,
            meta_description: $learningPath->meta_description,
            meta_keywords: $learningPath->meta_keywords,
            display_order: (int) $learningPath->display_order,
            status: $learningPath->status,
            steps: $resolvedSteps
                ->map(static fn (ResolvedLearningPathStep $resolved): LearningPathStepData => LearningPathStepData::fromResolved($resolved))
                ->all(),
            media: $learningPath->getAllMedia(urlOnly: true, onlyTags: [MediaTagEnum::COVER, MediaTagEnum::GALLERY]),
        );
    }
}
