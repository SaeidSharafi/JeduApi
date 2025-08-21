<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Discounts\DiscountPromotionData;
use App\Http\Controllers\Controller;
use App\Models\DiscountPromotion;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Discount Promotion Status
 *
 * Toggle the active status of a discount promotion.
 *
 * @authenticated Staff
 */
class DiscountPromotionStatusUpdateController extends Controller
{
    /**
     * Toggle the active status of a discount promotion.
     *
     * @responseFile 200 responses/discount/promotion.status_update.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
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
