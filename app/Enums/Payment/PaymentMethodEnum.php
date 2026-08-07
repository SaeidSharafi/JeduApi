<?php

declare(strict_types=1);

namespace App\Enums\Payment;

use App\Data\Admin\Settings\Gateway\DigipayGatewaySettingData;
use App\Data\Admin\Settings\Gateway\MellatGatewaySettingData;
use App\Enums\System\SettingKeyEnum;
use App\Traits\AdvanceEnum;

enum PaymentMethodEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case BANK_TRANSFER  = 'bank_transfer';
    case MELLAT_GATEWAY = 'mellat_gateway';
    // case CASH_ON_DELIVERY = 'cash_on_delivery';
    case WALLET     = 'wallet';
    case NO_PAYMENT = 'no_payment';
    case DIGIPAY    = 'digipay';

    public function settingDataClass(): ?string
    {
        return match ($this) {
            self::MELLAT_GATEWAY => MellatGatewaySettingData::class,
            self::DIGIPAY        => DigipayGatewaySettingData::class,
            default              => null, // Simple gateways need no specialized configuration DTO
        };
    }

    public function settingKey(): ?SettingKeyEnum
    {
        return match ($this) {
            self::MELLAT_GATEWAY => SettingKeyEnum::MELLAT,
            self::WALLET         => SettingKeyEnum::WALLET,
            self::BANK_TRANSFER  => SettingKeyEnum::BANK_TRANSFER,
            self::DIGIPAY        => SettingKeyEnum::DIGIPAY,
            default              => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return match ($this) {
            self::MELLAT_GATEWAY => config('payments.mellat'),
            self::WALLET         => config('payments.wallet'),
            self::BANK_TRANSFER  => config('payments.bank_transfer'),
            self::DIGIPAY        => config('payments.digipay'),
            default              => [],
        };
    }
}
