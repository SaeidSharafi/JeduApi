<?php

declare(strict_types=1);

namespace App\Data\Admin\LearningPath;

use App\Enums\System\MorphTypeEnum;
use App\Models\LearningPathStep;
use Spatie\LaravelData\Data;

final class LearningPathStepData extends Data
{
    public function __construct(
        public int $id,
        public int $position,
        public string $productable_type,
        public int $productable_id,
        public string $title,
        public string $description,
        public ?LearningPathProductableData $productable = null,
    ) {}

    public static function fromModel(LearningPathStep $step): self
    {
        $type = $step->productable_type;
        if (class_exists($type)) {
            $type = MorphTypeEnum::getAlias($type) ?? $type;
        }

        return self::factory()->withoutMagicalCreation()->from([
            'id'               => $step->getKey(),
            'position'         => $step->position,
            'productable_type' => $type,
            'productable_id'   => $step->productable_id,
            'title'            => $step->title,
            'description'      => $step->description,
            'productable'      => $step->relationLoaded('productable') && $step->productable !== null
                ? LearningPathProductableData::fromModel($step->productable, $type)
                : null,
        ]);
    }
}
