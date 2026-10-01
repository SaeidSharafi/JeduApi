<?php

declare(strict_types=1);

namespace App\Services\LearningPath;

use App\Data\Shop\ProductPriceData;
use App\Enums\LearningPathStepActionStateEnum;
use App\Models\LearningPathStep;
use App\Models\Product;

final readonly class ResolvedLearningPathStep
{
    public function __construct(
        public LearningPathStep $step,
        public ?Product $product,
        public ?ProductPriceData $priceData,
        public bool $available,
    ) {}

    public function state(): LearningPathStepActionStateEnum
    {
        return match (true) {
            $this->product === null => LearningPathStepActionStateEnum::COMING_SOON,
            $this->available        => LearningPathStepActionStateEnum::AVAILABLE,
            default                 => LearningPathStepActionStateEnum::UNAVAILABLE,
        };
    }
}
