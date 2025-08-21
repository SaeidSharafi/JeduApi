<?php

namespace App\Http\Controllers\Api\Admin;

use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderPreviewData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiSuccessResponse;
use App\Services\Discounts\OrderCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Orders
 *
 * @authenticated
 */
class OrderCalculationController extends Controller
{
    /**
     *
     * Preview the order calculation based on the provided data.
     *
     * @param OrderCreateData $data
     * @param OrderCalculationService $orderCalculationService
     * @return ApiSuccessResponse
     *
     * @responseFile 200 responses/order/preview.json
     */
    public function __invoke(OrderCreateData $data, OrderCalculationService $orderCalculationService): ApiSuccessResponse
    {

        $context = $orderCalculationService->calculate($data);

        return response()->success(OrderPreviewData::fromOrderContext($context));
    }
}
