<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Promotion;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\DiscountPromotion;
use App\Services\Discounts\DiscountMetadataService;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Discount Info
 *
 * APIs for retrieving discount metadata, conditions, actions, operators, and types.
 *
 * @authenticated Staff
 */
final class DiscountInfoController extends Controller
{
    public function __construct(
        private readonly DiscountMetadataService $discountMetadataService
    ) {}

    /**
     * Get all discount metadata (conditions & actions for cart and product).
     *
     * @responseFile 200 resources/responses/admin/discount/info.index.json
     * @responseFile 403 resources/responses/403.json
     */
    public function index(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return apiResponse()->success($this->discountMetadataService->getMetadata());
    }

    /**
     * Get available discount conditions and their configurations dynamically.
     *
     * @responseFile 200 resources/responses/admin/discount/info.conditions.json
     * @responseFile 403 resources/responses/403.json
     */
    public function conditions(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return apiResponse()->success($this->discountMetadataService->getConditions());
    }

    /**
     * Get available discount actions and their configurations dynamically.
     *
     * @responseFile 200 resources/responses/admin/discount/info.actions.json
     * @responseFile 403 resources/responses/403.json
     */
    public function actions(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return apiResponse()->success($this->discountMetadataService->getActions());
    }

    /**
     * Get all available discount operators.
     *
     * @responseFile 200 resources/responses/admin/discount/info.operators.json
     * @responseFile 403 resources/responses/403.json
     */
    public function operators(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return apiResponse()->success($this->discountMetadataService->getOperators());
    }

    /**
     * Get discount promotion types.
     *
     * @responseFile 200 resources/responses/admin/discount/info.types.json
     * @responseFile 403 resources/responses/403.json
     */
    public function types(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return apiResponse()->success($this->discountMetadataService->getTypes());
    }
}
