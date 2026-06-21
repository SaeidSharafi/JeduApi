<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Sale;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Payment\GatewayData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingsService;

/**
 * @group Shop - Gateways
 *
 */
final class GatewayListController extends Controller
{

    /**
     * List active gateways
     *
     * @responseFile 200 resources/responses/shop/gateway/index.json
     */
    public function __invoke(SettingsService $service): ApiResponseInterface
    {
        $gateways = null;
        foreach (PaymentMethodEnum::cases() as $method) {
            if (null === $method->settingKey()){
                continue;
            }
            $gatewayData = $service->get($method->settingKey());
            $gatewayData = $gatewayData ? GatewayData::from($gatewayData) : null;
            if ($gatewayData && $gatewayData->enabled && $gatewayData->shop_enabled) {
                $gateways[] = [
                    'method' => $method->value,
                    ...$gatewayData->toArray()
                ];
            }
        }

        return response()->success($gateways);
    }
}
