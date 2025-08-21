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

/**
 * @group Admin - Discount Promotion Management
 *
 * APIs for managing discount promotions (CRUD).
 *
 * @authenticated Staff
 */
final class DiscountPromotionController extends Controller
{
    /**
     * List discount promotions.
     *
     * @queryParam filter[is_active] boolean Filter by active status. Example: true
     * @queryParam filter[type] string Filter by promotion type. Example: product_specific
     * @queryParam filter[search] string Search by name. Example: "Back to School"
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Results per page. Example: 15
     *
     * @responseFile 200 responses/discount/promotion.index.json
     * @responseFile 403 responses/403.json
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
     * Create a new discount promotion.
     *
     *
     * @responseFile 201 responses/discount/promotion.store.json
     * @responseFile 403 responses/403.json
     * @responseFile 422 responses/discount/promotion.422.json
     */
    public function store(DiscountPromotionCreateData $data, CreateDiscountPromotionAction $action): ApiResponseInterface
    {
        Gate::authorize('create', DiscountPromotion::class);
        $promotion = $action->execute($data);
        return response()->created(DiscountPromotionData::from($promotion), model: DiscountPromotion::class);
    }

    /**
     * Show a discount promotion.
     *
     * @responseFile 200 responses/discount/promotion.show.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function show(DiscountPromotion $discountPromotion): ApiResponseInterface
    {
        Gate::authorize('view', $discountPromotion);
        $discountPromotion->load(['rules', 'coupons']);
        return response()->success(DiscountPromotionData::from($discountPromotion));
    }

    /**
     * Update a discount promotion.
     *
     * @responseFile 200 responses/discount/promotion.update.json
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     * @responseFile 422 responses/discount/promotion.422.json
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
     * Delete a discount promotion.
     *
     * @urlParam discountPromotion integer required The ID of the promotion. Example: 1
     *
     * @response 204
     * @responseFile 403 responses/403.json
     * @responseFile 404 responses/404.json
     */
    public function destroy(DiscountPromotion $discountPromotion, DeleteDiscountPromotionAction $action): JsonResponse
    {
        Gate::authorize('delete', $discountPromotion);
        $action->execute($discountPromotion);
        return response()->noContentJson();
    }
}
