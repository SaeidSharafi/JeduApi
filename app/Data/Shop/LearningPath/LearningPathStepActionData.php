<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\LearningPathStepActionStateEnum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class LearningPathStepActionData extends Data
{
    public function __construct(
        public string $type,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public LearningPathStepActionStateEnum $state,
        public bool $enabled,
    ) {}

    public static function fromState(LearningPathStepActionStateEnum $state): self
    {
        return new self(
            type: $state->actionType(),
            state: $state,
            enabled: $state->isEnabled(),
        );
    }
}
