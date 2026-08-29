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
        private ?bool $simulatorAvailable = null,
    ) {}

    /**
     * @return array<GatewayData>
     */
    public function getShopActiveGatewaysDetails(): array
    {
        $gateways = [];
        if ($this->simulatorIsAvailable()) {
            $gateways[] = $this->simulatorGateway()->toArray();
        }

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
        if ($this->simulatorIsAvailable()) {
            $gateways[] = PaymentMethodEnum::SIMULATOR->value;
        }

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

    private function simulatorIsAvailable(): bool
    {
        return ($this->simulatorAvailable ?? app()->environment('e2e'))
            && (bool) config('payments.simulator.enabled');
    }

    private function simulatorGateway(): GatewayData
    {
        return GatewayData::from([
            'key'          => PaymentMethodEnum::SIMULATOR->value,
            'enabled'      => true,
            'shop_enabled' => true,
            'label'        => config('payments.simulator.label'),
            'description'  => config('payments.simulator.description'),
            'icon_url'     => config('payments.simulator.icon'),
        ]);
    }
}
