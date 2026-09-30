<?php

declare(strict_types=1);

namespace App\Data\Shop\LearningPath;

use App\Data\Shop\ProductPriceData;
use App\Enums\System\MorphTypeEnum;
use App\Models\LearningPathStep;
use App\Models\Product;
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
        public ?LearningPathProductableData $productable,
        public ?LearningPathCurrentProductData $current_product,
        public LearningPathStepActionData $action,
    ) {}

    public static function fromModel(LearningPathStep $step): self
    {
        $type = (string) $step->productable_type;
        if (class_exists($type)) {
            $type = MorphTypeEnum::getAlias($type) ?? $type;
        }

        $productable    = $step->relationLoaded('productable') ? $step->productable : null;
        $currentProduct = $step->relationLoaded('currentProduct')
            ? $step->getRelation('currentProduct')
            : null;
        $priceData     = $step->getAttribute('current_product_price_data');
        $priceData     = $priceData instanceof ProductPriceData ? $priceData : null;
        $actionEnabled = (bool) $step->getAttribute('current_product_action_enabled');

        return self::factory()->withoutMagicalCreation()->from([
            'id'               => (int) $step->getKey(),
            'position'         => (int) $step->position,
            'productable_type' => $type,
            'productable_id'   => (int) $step->productable_id,
            'title'            => $step->title,
            'description'      => $step->description,
            'productable'      => $productable === null
                ? null
                : LearningPathProductableData::fromModel($productable, $type),
            'current_product' => $currentProduct instanceof Product
                ? LearningPathCurrentProductData::fromModels($currentProduct, $productable, $priceData, $type)
                : null,
            'action' => LearningPathStepActionData::fromResolution($currentProduct, $actionEnabled),
        ]);
    }
}
