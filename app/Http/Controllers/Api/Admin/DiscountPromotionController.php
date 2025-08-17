<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Discounts\CreateDiscountPromotionAction;
use App\Actions\Admin\Discounts\DeleteDiscountPromotionAction;
use App\Actions\Admin\Discounts\UpdateDiscountPromotionAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\Discounts\DiscountPromotionCreateData;
use App\Data\Admin\Discounts\DiscountPromotionData;
use App\Http\Controllers\Controller;
use App\Models\DiscountPromotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class DiscountPromotionController extends Controller
{
    /**
     * Display a listing of the discount promotions.
     */
    public function index(Request $request): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        $promotions = QueryBuilder::for(DiscountPromotion::class)
            ->allowedFilters([
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('type'),
                AllowedFilter::partial('search', 'name'),
            ])
            ->with(['rules', 'coupons'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();


        return response()->success(DiscountPromotionData::collect($promotions));
    }

    /**
     * Store a newly created discount promotion.
     */
    public function store(DiscountPromotionCreateData $data, CreateDiscountPromotionAction $action): ApiResponseInterface
    {
        Gate::authorize('create', DiscountPromotion::class);

        $promotion = $action->execute($data);

        return response()->created(DiscountPromotionData::from($promotion), model: DiscountPromotion::class);
    }

    /**
     * Display the specified discount promotion.
     */
    public function show(DiscountPromotion $discountPromotion): ApiResponseInterface
    {
        Gate::authorize('view', $discountPromotion);

        $discountPromotion->load(['rules', 'coupons']);

        return response()->success(DiscountPromotionData::from($discountPromotion));
    }

    /**
     * Update the specified discount promotion.
     */
    public function update(
        DiscountPromotionCreateData $data,
        DiscountPromotion $discountPromotion,
        UpdateDiscountPromotionAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $discountPromotion);

        $updatedPromotion = $action->execute($discountPromotion, $data);

        return response()->success(DiscountPromotionData::from($updatedPromotion));
    }

    /**
     * Remove the specified discount promotion.
     */
    public function destroy(DiscountPromotion $discountPromotion, DeleteDiscountPromotionAction $action): JsonResponse
    {
        Gate::authorize('delete', $discountPromotion);

        $action->execute($discountPromotion);

        return response()->noContentJson();
    }
}
