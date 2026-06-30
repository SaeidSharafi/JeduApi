<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Promotion;

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
final class DiscountPromotionStatusUpdateController extends Controller
{
    /**
     * Toggle the active status of a discount promotion.
     *
     * @responseFile 200 resources/responses/admin/discount/promotion.status_update.json
     * @responseFile 403 resources/responses/403.json
     * @responseFile 404 resources/responses/404.json
     */
    public function __invoke(DiscountPromotion $discountPromotion): ApiResponseInterface
    {
        Gate::authorize('update', $discountPromotion);
        $discountPromotion->update([
            'is_active' => ! $discountPromotion->is_active,
        ]);
        $discountPromotion->load('rules', 'coupons');

        return apiResponse()->success(DiscountPromotionData::from($discountPromotion->fresh()));
    }
}
