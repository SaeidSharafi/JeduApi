<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum LearningPathStepActionStateEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case COMING_SOON = 'coming_soon';
    case AVAILABLE   = 'available';
    case UNAVAILABLE = 'unavailable';

    public function actionType(): string
    {
        return match ($this) {
            self::COMING_SOON                  => 'coming_soon',
            self::AVAILABLE, self::UNAVAILABLE => 'view_product',
        };
    }

    public function isEnabled(): bool
    {
        return $this === self::AVAILABLE;
    }
}
