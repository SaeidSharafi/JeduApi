<?php

namespace App\Services\Discounts\Cart\Conditions;

use App\Data\Admin\Discounts\OrderContextData;
use App\Services\Discounts\Configs\UserNeverPurchasedCategoryData;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Models\Enrollment;
use App\Attributes\DiscountHandlerKey;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelData\Data;

#[DiscountHandlerKey('user_never_purchased_category')]
class UserNeverPurchasedCategoryCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return UserNeverPurchasedCategoryData::class;
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        if (!$context->customer?->id) {
            return false;
        }

        /** @var UserNeverPurchasedCategoryData $configuration */
        $hasPurchased = Enrollment::where('customer_id', $context->customer->id)
            ->whereHas('productDeliveryOption.product.categories', function (Builder $query) use ($configuration): void {
                $query->whereIn('categories.id', $configuration->category_ids);
            })
            ->exists();

        return !$hasPurchased;
    }
}
