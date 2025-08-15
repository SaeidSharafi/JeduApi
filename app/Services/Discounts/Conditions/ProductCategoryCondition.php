<?php

declare(strict_types=1);

namespace App\Services\Discounts\Conditions;

use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Data;

final class ProductCategoryCondition implements DiscountConditionContract
{
    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (! $configuration instanceof ProductCategoryConditionConfigData) {
            return false;
        }
        if (empty($configuration->category_ids)) {
            return true; // No categories specified, so the condition vacuously passes
        }

        $itemProductIds = $context->items
            ->pluck('product_delivery_option.product.id')
            ->unique()
            ->all();

        if (empty($itemProductIds)) {
            return false;
        }

        // Find how many of the cart's products match the required categories
        $matchingProductCount = DB::table('categorizables')
            ->where('categorizable_type', 'product')
            ->whereIn('categorizable_id', $itemProductIds)
            ->whereIn('category_id', $configuration->category_ids)
            ->distinct('categorizable_id')
            ->count();

        return match ($configuration->match_policy) {
            'any'   => $matchingProductCount > 0,
            'all'   => $matchingProductCount === count($itemProductIds),
            default => false,
        };
    }
}
