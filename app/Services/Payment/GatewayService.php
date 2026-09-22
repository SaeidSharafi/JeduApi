<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Data\Shop\Payment\GatewayData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Services\SettingsService;

final readonly class GatewayService
{
    public function __construct(
        private SettingsService $settings,
        private ?bool $simulatorAvailable = null,
    ) {}

    /**
     * Active gateways for the given purpose, as shop-facing DTOs.
     *
     * @return array<GatewayData>
     */
    public function getShopActiveGatewaysDetails(PaymentPurposeEnum $purpose = PaymentPurposeEnum::ORDER): array
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

            if (! $this->isEligible($gatewayData, $purpose)) {
                continue;
            }

            $gatewayData['key'] = $method->value;
            $gateways[]         = GatewayData::from($gatewayData)->toArray();
        }

        return $gateways;
    }

    /**
     * Active gateway method values for the given purpose.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getShopActiveGateways(PaymentPurposeEnum $purpose = PaymentPurposeEnum::ORDER): array
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

            if ($this->isEligible($gatewayData, $purpose)) {
                $gateways[] = $method->value;
            }
        }

        return $gateways;
    }

    /**
     * Whether a gateway may be offered for the given purpose.
     *
     * `enabled` is the master switch for every purpose. Each purpose additionally
     * requires its own flag: `shop_enabled` for checkout, `wallet_topup_enabled`
     * for wallet top-up. The two context flags are deliberately independent, so a
     * gateway can be available for top-up without being offered at checkout.
     *
     * @param  array<string, mixed>  $gatewayData
     */
    private function isEligible(array $gatewayData, PaymentPurposeEnum $purpose): bool
    {
        if (! (bool) ($gatewayData['enabled'] ?? false)) {
            return false;
        }

        return match ($purpose) {
            PaymentPurposeEnum::ORDER        => (bool) ($gatewayData['shop_enabled'] ?? false),
            PaymentPurposeEnum::WALLET_TOPUP => (bool) ($gatewayData['wallet_topup_enabled'] ?? false),
        };
    }

    private function simulatorIsAvailable(): bool
    {
        return ($this->simulatorAvailable ?? app()->environment('e2e'))
            && (bool) config('payments.simulator.enabled');
    }

    private function simulatorGateway(): GatewayData
    {
        return GatewayData::from([
            'key'                  => PaymentMethodEnum::SIMULATOR->value,
            'enabled'              => true,
            'shop_enabled'         => true,
            'wallet_topup_enabled' => true,
            'label'                => config('payments.simulator.label'),
            'description'          => config('payments.simulator.description'),
            'icon_url'             => config('payments.simulator.icon'),
        ]);
    }
}
