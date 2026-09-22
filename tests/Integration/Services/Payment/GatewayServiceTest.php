<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Services\Payment\GatewayService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

covers(GatewayService::class);

/**
 * Build a gateway Setting value with the exact keys GatewayData expects.
 *
 * @return array<string, mixed>
 */
function gatewaySettingRow(SettingKeyEnum $key, bool $enabled = true, bool $shopEnabled = true, bool $walletTopupEnabled = true): array
{
    return [
        'enabled'              => $enabled,
        'shop_enabled'         => $shopEnabled,
        'wallet_topup_enabled' => $walletTopupEnabled,
        'label'                => 'gateway-'.$key->value,
        'description'          => null,
        'icon_url'             => null,
    ];
}

/**
 * Persist a Setting row for every gateway that has a setting key, optionally
 * disabling one, hiding one from the shop, or blocking one from wallet top-up.
 */
function createGatewaySettings(
    ?SettingKeyEnum $disabledKey = null,
    ?SettingKeyEnum $shopDisabledKey = null,
    ?SettingKeyEnum $walletTopupDisabledKey = null,
): void {
    foreach (PaymentMethodEnum::cases() as $method) {
        if ($method->settingKey() === null) {
            continue;
        }

        $key = $method->settingKey();

        Setting::factory()->create([
            'key'   => $key->value,
            'value' => gatewaySettingRow(
                $key,
                enabled: $key            !== $disabledKey,
                shopEnabled: $key        !== $shopDisabledKey,
                walletTopupEnabled: $key !== $walletTopupDisabledKey,
            ),
        ]);
    }
}

describe('GatewayService', function (): void {
    beforeEach(function (): void {
        // SettingsService caches all settings forever — keep tests isolated.
        Cache::flush();
    });

    afterEach(function (): void {
        config([
            'payments.simulator.enabled' => false,
            'payments.simulator.icon'    => null,
        ]);
    });

    it('advertises the simulator only in E2E', function (): void {
        $service = new GatewayService(app(SettingsService::class), false);

        expect($service->getShopActiveGateways())->not->toContain(PaymentMethodEnum::SIMULATOR->value);

        config([
            'payments.simulator.enabled' => true,
            'payments.simulator.icon'    => '/storage/fake-media/simulator.svg',
        ]);
        $service = new GatewayService(app(SettingsService::class), true);

        expect($service->getShopActiveGateways())->toContain(PaymentMethodEnum::SIMULATOR->value);
        expect($service->getShopActiveGateways(PaymentPurposeEnum::WALLET_TOPUP))->toContain(PaymentMethodEnum::SIMULATOR->value);
        expect(collect($service->getShopActiveGatewaysDetails())->firstWhere('key', 'simulator'))
            ->toMatchArray([
                'key'                  => 'simulator',
                'enabled'              => true,
                'shop_enabled'         => true,
                'wallet_topup_enabled' => true,
                'label'                => config('payments.simulator.label'),
                'description'          => config('payments.simulator.description'),
                'icon_url'             => config('payments.simulator.icon'),
            ]);
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

        // The master switch outranks any context flag: config marks Mellat top-up
        // eligible, yet the disabled setting removes it from the top-up list too.
        expect($service->getShopActiveGateways(PaymentPurposeEnum::WALLET_TOPUP))
            ->toBe(['bank_transfer', 'wallet', 'digipay'])
            ->not->toContain('mellat_gateway');
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
            ->and($digipay['label'])->toBe('gateway-'.SettingKeyEnum::DIGIPAY->value)
            ->and($digipay['enabled'])->toBeTrue()
            ->and($digipay['shop_enabled'])->toBeTrue()
            ->and($digipay['wallet_topup_enabled'])->toBeTrue();
    });

    it('excludes gateways not marked for wallet top-up from the top-up list', function (): void {
        createGatewaySettings(walletTopupDisabledKey: SettingKeyEnum::WALLET);
        $service = app(GatewayService::class);

        expect($service->getShopActiveGateways(PaymentPurposeEnum::WALLET_TOPUP))
            ->toBe(['bank_transfer', 'mellat_gateway', 'digipay'])
            ->not->toContain('wallet');

        expect(collect($service->getShopActiveGatewaysDetails(PaymentPurposeEnum::WALLET_TOPUP))->pluck('key')->all())
            ->toBe(['bank_transfer', 'mellat_gateway', 'digipay']);

        // The checkout list still offers the wallet — eligibility is per purpose.
        expect($service->getShopActiveGateways(PaymentPurposeEnum::ORDER))->toContain('wallet');
    });

    it('defaults the purpose to checkout', function (): void {
        createGatewaySettings(walletTopupDisabledKey: SettingKeyEnum::WALLET);
        $service = app(GatewayService::class);

        expect($service->getShopActiveGateways())->toBe(['bank_transfer', 'mellat_gateway', 'wallet', 'digipay'])
            ->and(collect($service->getShopActiveGatewaysDetails())->pluck('key')->all())
            ->toBe(['bank_transfer', 'mellat_gateway', 'wallet', 'digipay']);
    });

    it('keeps wallet top-up independent of shop_enabled', function (): void {
        createGatewaySettings(shopDisabledKey: SettingKeyEnum::MELLAT);
        $service = app(GatewayService::class);

        // Hidden from checkout but still allowed to fund a wallet.
        expect($service->getShopActiveGateways(PaymentPurposeEnum::ORDER))
            ->toBe(['bank_transfer', 'wallet', 'digipay'])
            ->and($service->getShopActiveGateways(PaymentPurposeEnum::WALLET_TOPUP))
            ->toBe(['bank_transfer', 'mellat_gateway', 'wallet', 'digipay']);
    });

    it('excludes a disabled gateway from the wallet top-up list', function (): void {
        createGatewaySettings(disabledKey: SettingKeyEnum::DIGIPAY);
        $service = app(GatewayService::class);

        expect($service->getShopActiveGateways(PaymentPurposeEnum::WALLET_TOPUP))
            ->toBe(['bank_transfer', 'mellat_gateway', 'wallet'])
            ->not->toContain('digipay');
    });

    it('falls back to config defaults for the wallet top-up list', function (): void {
        $service = app(GatewayService::class);

        // config/payments.php marks wallet and bank_transfer as top-up ineligible.
        expect($service->getShopActiveGateways(PaymentPurposeEnum::WALLET_TOPUP))
            ->toBe(['mellat_gateway', 'digipay']);
    });
});
