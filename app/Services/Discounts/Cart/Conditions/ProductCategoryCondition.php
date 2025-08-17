<?php

declare(strict_types=1);

namespace App\Services\Discounts\Cart\Conditions;

use App\Attributes\DiscountHandler;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\ProductCategoryConditionConfigData;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Data;

#[DiscountHandler('product_in_category', 'condition')]
final class ProductCategoryCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return ProductCategoryConditionConfigData::class;
    }

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
