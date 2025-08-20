<?php
declare(strict_types=1);

namespace App\Services\Discounts\Product\Conditions;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Configs\ProductCategoryConditionConfigData;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('product_in_category')]
final class ProductCategoryCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return ProductCategoryConditionConfigData::class;
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        if (! $configuration instanceof ProductCategoryConditionConfigData) {
            return false;
        }
        if (empty($configuration->category_ids)) {
            return true; // No categories specified, so the condition vacuously passes
        }

        $productId = $option->product->id;
        if (!$productId) {
            return false;
        }

        $matching = DB::table('categorizables')
            ->where('categorizable_type', 'product')
            ->where('categorizable_id', $productId)
            ->whereIn('category_id', $configuration->category_ids)
            ->count();

        // For a single product, 'any' and 'all' are equivalent: must match at least one
        return $matching > 0;
    }
}
