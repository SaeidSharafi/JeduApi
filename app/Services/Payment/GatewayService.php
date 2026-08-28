<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Data\Shop\Payment\GatewayData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Services\SettingsService;

final readonly class GatewayService
{
    public function __construct(
        private SettingsService $settings,
    ) {}

    /**
     * @return array<GatewayData>
     */
    public function getShopActiveGatewaysDetails(): array
    {
        $gateways = [];
        foreach (PaymentMethodEnum::cases() as $method) {
            if ($method->settingKey() === null) {
                continue;
            }

            $gatewayData = $this->settings->get($method->settingKey(), $method->defaultConfig());
            if (! $gatewayData) {
                continue;
            }

            $gatewayData['key'] = $method->value;
            $gatewayData        = GatewayData::from($gatewayData);
            if ($gatewayData->enabled && $gatewayData->shop_enabled) {
                $gateways[] = $gatewayData->toArray();
            }
        }

        return $gateways;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getShopActiveGateways(): array
    {
        $gateways = [];
        foreach (PaymentMethodEnum::cases() as $method) {
            if ($method->settingKey() === null) {
                continue;
            }
            $gatewayData = $this->settings->get($method->settingKey(), $method->defaultConfig());
            if (! $gatewayData) {
                continue;
            }

            if ($gatewayData['enabled'] && $gatewayData['shop_enabled']) {
                $gateways[] = $method->value;
            }
        }

        return $gateways;
    }
}
