<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Models\Product;
use Spatie\LaravelData\Data;

final class LearningPathStepActionData extends Data
{
    public function __construct(
        public string $type,
        public string $state,
        public bool $enabled,
    ) {}

    public static function fromResolution(?Product $product, bool $enabled): self
    {
        return self::factory()->withoutMagicalCreation()->from([
            'type'    => $product === null ? 'coming_soon' : 'view_product',
            'state'   => $product === null ? 'coming_soon' : ($enabled ? 'available' : 'unavailable'),
            'enabled' => $product !== null && $enabled,
        ]);
    }
}
