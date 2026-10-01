<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Enums\System\MorphTypeEnum;
use App\Services\LearningPath\ResolvedLearningPathStep;
use Spatie\LaravelData\Data;

final class LearningPathStepData extends Data
{
    public function __construct(
        public int $position,
        public string $productable_type,
        public int $productable_id,
        public string $title,
        public string $description,
        public LearningPathProductData $product,
        public LearningPathStepActionData $action,
    ) {}

    public static function fromResolved(ResolvedLearningPathStep $resolved): self
    {
        $step = $resolved->step;
        $type = (string) $step->productable_type;

        if (class_exists($type)) {
            $type = MorphTypeEnum::getAlias($type) ?? $type;
        }

        return new self(
            position: (int) $step->position,
            productable_type: $type,
            productable_id: (int) $step->productable_id,
            title: $step->title,
            description: $step->description,
            product: LearningPathProductData::fromResolved($resolved, $type),
            action: LearningPathStepActionData::fromState($resolved->state()),
        );
    }
}
