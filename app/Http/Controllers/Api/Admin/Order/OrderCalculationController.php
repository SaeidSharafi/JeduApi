<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Order;

use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderPreviewData;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiSuccessResponse;
use App\Services\Discounts\OrderCalculationService;

/**
 * @group Admin - Orders
 *
 * @authenticated
 */
final class OrderCalculationController extends Controller
{
    /**
     * Preview the order calculation based on the provided data.
     *
     *
     * @responseFile 200 resources/responses/admin/order/preview.json
     */
    public function __invoke(OrderCreateData $data, OrderCalculationService $orderCalculationService): ApiSuccessResponse
    {

        $context = $orderCalculationService->calculate($data);

        return apiResponse()->success(OrderPreviewData::fromOrderContext($context));
    }
}
