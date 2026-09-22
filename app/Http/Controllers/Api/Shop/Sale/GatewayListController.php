<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Payment\GatewayListRequestData;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Http\Controllers\Controller;
use App\Services\Payment\GatewayService;

/**
 * @group Shop - Gateways
 */
final class GatewayListController extends Controller
{
    /**
     * List active gateways
     *
     * Returns the gateways available for the requested purpose. Omit `purpose`
     * (or pass `order`) for the checkout list; pass `wallet_topup` for the
     * wallet top-up list. A gateway may appear in one list and not the other.
     *
     * @queryParam purpose string Which list to return. Enum: `order`, `wallet_topup`. Example: wallet_topup
     *
     * @responseFile 200 resources/responses/shop/gateway/index.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(GatewayListRequestData $data, GatewayService $service): ApiResponseInterface
    {
        return apiResponse()->success(
            $service->getShopActiveGatewaysDetails($data->purpose ?? PaymentPurposeEnum::ORDER)
        );
    }
}
