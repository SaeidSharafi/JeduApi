<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Payment\GatewayData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Services\Payment\GatewayService;
use App\Services\SettingsService;

/**
 * @group Shop - Gateways
 */
final class GatewayListController extends Controller
{
    /**
     * List active gateways
     *
     * @responseFile 200 resources/responses/shop/gateway/index.json
     */
    public function __invoke(GatewayService $service): ApiResponseInterface
    {
        return apiResponse()->success($service->getShopActiveGatewaysDetails());
    }
}
