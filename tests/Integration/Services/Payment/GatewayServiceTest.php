<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Services\Payment\GatewayService;
use Illuminate\Support\Facades\Cache;

/**
 * Build a gateway Setting value with the exact keys GatewayData expects.
 *
 * @return array<string, mixed>
 */
function gatewaySettingRow(SettingKeyEnum $key, bool $enabled = true, bool $shopEnabled = true): array
{
    return [
        'enabled'      => $enabled,
        'shop_enabled' => $shopEnabled,
        'label'        => 'gateway-'.$key->value,
        'description'  => null,
        'icon_url'     => null,
    ];
}

/**
 * Persist a Setting row for every gateway that has a setting key, optionally
 * disabling one or hiding one from the shop.
 */
function createGatewaySettings(?SettingKeyEnum $disabledKey = null, ?SettingKeyEnum $shopDisabledKey = null): void
{
    foreach (PaymentMethodEnum::cases() as $method) {
        if ($method->settingKey() === null) {
            continue;
        }

        $key = $method->settingKey();

        Setting::factory()->create([
            'key'   => $key->value,
            'value' => gatewaySettingRow(
                $key,
                enabled: $key     !== $disabledKey,
                shopEnabled: $key !== $shopDisabledKey,
            ),
        ]);
    }
}

describe('GatewayService', function (): void {
    beforeEach(function (): void {
        // SettingsService caches all settings forever — keep tests isolated.
        Cache::flush();
    });

    it('returns every gateway that has a setting key when no settings exist', function (): void {
        $service = app(GatewayService::class);

        $gateways = $service->getShopActiveGateways();

        // Enum order: BANK_TRANSFER, MELLAT_GATEWAY, WALLET, NO_PAYMENT, DIGIPAY.
        // NO_PAYMENT has no setting key and must never appear.
        expect($gateways)->toBe(['bank_transfer', 'mellat_gateway', 'wallet', 'digipay'])
            ->not->toContain('no_payment');
    });

    it('returns gateway details with all expected fields for active gateways', function (): void {
        createGatewaySettings();
        $service = app(GatewayService::class);

        /** @var array<int, array<string, mixed>> $details */
        $details = $service->getShopActiveGatewaysDetails();

        expect(collect($details)->pluck('key')->all())
            ->toBe(['bank_transfer', 'mellat_gateway', 'wallet', 'digipay']);

        foreach ($details as $gateway) {
            expect($gateway)->toHaveKeys(['key', 'enabled', 'shop_enabled', 'label', 'description', 'icon_url'])
                ->and($gateway['enabled'])->toBeTrue()
                ->and($gateway['shop_enabled'])->toBeTrue()
                ->and($gateway['description'])->toBeNull()
                ->and($gateway['icon_url'])->toBeNull()
                ->and($gateway['label'])->toBeString();
        }
    });

    it('excludes a gateway disabled through its setting', function (): void {
        createGatewaySettings(disabledKey: SettingKeyEnum::MELLAT);
        $service = app(GatewayService::class);

        expect($service->getShopActiveGateways())
            ->toBe(['bank_transfer', 'wallet', 'digipay'])
            ->not->toContain('mellat_gateway');

        expect(collect($service->getShopActiveGatewaysDetails())->pluck('key')->all())
            ->toBe(['bank_transfer', 'wallet', 'digipay']);
    });

    it('excludes a gateway that is enabled but not enabled for the shop', function (): void {
        createGatewaySettings(shopDisabledKey: SettingKeyEnum::WALLET);
        $service = app(GatewayService::class);

        expect($service->getShopActiveGateways())
            ->toBe(['bank_transfer', 'mellat_gateway', 'digipay'])
            ->not->toContain('wallet');

        expect(collect($service->getShopActiveGatewaysDetails())->pluck('key')->all())
            ->toBe(['bank_transfer', 'mellat_gateway', 'digipay']);
    });

    it('includes the gateway key inside each details entry', function (): void {
        createGatewaySettings();
        $service = app(GatewayService::class);

        /** @var array<string, mixed>|null $digipay */
        $digipay = collect($service->getShopActiveGatewaysDetails())
            ->firstWhere('key', 'digipay');

        expect($digipay)->not->toBeNull()
            ->and($digipay['key'])->toBe('digipay')
            ->and($digipay['label'])->toBe('gateway-payment.digipay')
            ->and($digipay['enabled'])->toBeTrue()
            ->and($digipay['shop_enabled'])->toBeTrue();
    });
});
