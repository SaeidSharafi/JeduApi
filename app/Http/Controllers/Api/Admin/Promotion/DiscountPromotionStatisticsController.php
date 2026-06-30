<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Promotion;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\DiscountPromotion;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Discount Promotion Statistics
 *
 * Get statistics about discount promotions.
 *
 * @authenticated Staff
 */
final class DiscountPromotionStatisticsController extends Controller
{
    /**
     * Get discount promotion statistics.
     *
     * @responseFile 200 resources/responses/admin/discount/promotion.statistics.json
     * @responseFile 403 resources/responses/403.json
     */
    public function __invoke(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);
        $stats = [
            'total_promotions'            => DiscountPromotion::count(),
            'active_promotions'           => DiscountPromotion::where('is_active', true)->count(),
            'inactive_promotions'         => DiscountPromotion::where('is_active', false)->count(),
            'product_specific_promotions' => DiscountPromotion::where('type', 'product_specific')->count(),
            'cart_checkout_promotions'    => DiscountPromotion::where('type', 'cart_checkout')->count(),
            'promotions_with_coupons'     => DiscountPromotion::whereHas('coupons')->count(),
            'promotions_without_coupons'  => DiscountPromotion::whereDoesntHave('coupons')->count(),
        ];

        return apiResponse()->success($stats);
    }
}
