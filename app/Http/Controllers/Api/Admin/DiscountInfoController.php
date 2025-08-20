<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Models\DiscountPromotion;
use App\Services\Discounts\DiscountMetadataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class DiscountInfoController extends Controller
{
    public function __construct(
        private readonly DiscountMetadataService $discountMetadataService
    ) {}

    public function index()
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return response()->success($this->discountMetadataService->getMetadata());
    }
    /**
     * Get available discount conditions and their configurations dynamically.
     */
    public function conditions(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return response()->success($this->discountMetadataService->getConditions());
    }

    /**
     * Get available discount actions and their configurations dynamically.
     */
    public function actions(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return response()->success($this->discountMetadataService->getActions());
    }

    /**
     * Get all available discount operators.
     */
    public function operators(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return response()->success($this->discountMetadataService->getOperators());
    }

    /**
     * Get discount promotion types.
     */
    public function types(): ApiResponseInterface
    {
        Gate::authorize('viewAny', DiscountPromotion::class);

        return response()->success($this->discountMetadataService->getTypes());
    }
}
