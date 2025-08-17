<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\DiscountPromotion;
use Illuminate\Support\Facades\Gate;

class DiscountPromotionStatisticsController extends Controller
{
    public function __invoke(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);
        $stats = [
            'total_promotions' => DiscountPromotion::count(),
            'active_promotions' => DiscountPromotion::where('is_active', true)->count(),
            'inactive_promotions' => DiscountPromotion::where('is_active', false)->count(),
            'product_specific_promotions' => DiscountPromotion::where('type', 'product_specific')->count(),
            'cart_checkout_promotions' => DiscountPromotion::where('type', 'cart_checkout')->count(),
            'promotions_with_coupons' => DiscountPromotion::whereHas('coupons')->count(),
            'promotions_without_coupons' => DiscountPromotion::whereDoesntHave('coupons')->count(),
        ];
        return response()->success($stats);

    }
}
