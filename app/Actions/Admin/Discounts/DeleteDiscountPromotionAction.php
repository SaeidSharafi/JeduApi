<?php

declare(strict_types=1);

namespace App\Actions\Admin\Discounts;

use App\Models\DiscountPromotion;
use Illuminate\Support\Facades\DB;

final class DeleteDiscountPromotionAction
{
    public function execute(DiscountPromotion $promotion): void
    {
        DB::transaction(function () use ($promotion) {
            // Delete related rules and coupons (cascading should handle this but being explicit)
            $promotion->rules()->delete();
            $promotion->coupons()->delete();

            // Delete the promotion
            $promotion->delete();
        });
    }
}
