<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Discounts\DiscountPromotionData;
use App\Http\Controllers\Controller;
use App\Models\DiscountPromotion;
use Illuminate\Support\Facades\Gate;

class DiscountPromotionStatusUpdateController extends Controller
{
    public function __invoke(DiscountPromotion $discountPromotion): ApiResponseInterface
    {
        Gate::authorize('update', $discountPromotion);

        $discountPromotion->update([
            'is_active' => !$discountPromotion->is_active,
        ]);
        $discountPromotion->load('rules','coupons');

        return response()->success(DiscountPromotionData::from($discountPromotion->fresh()));
    }
}
