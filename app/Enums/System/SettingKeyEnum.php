<?php

declare(strict_types=1);

namespace App\Enums\System;

enum SettingKeyEnum: string
{
    case ABOUT_US          = 'about_us';
    case CONTACT_INFO      = 'contact_info';
    case COLLABORATION     = 'collaboration';
    case HEADER            = 'header';
    case FOOTER            = 'footer';
    case RULES             = 'rules';
    case SLIDERS           = 'sliders';
    case HOME_PAGE_BLOCKS  = 'home_page_blocks';
    case IMS               = 'ims';
    case MOODLE            = 'moodle';
    case BIG_BLUE_BUTTON   = 'big_blue_button';
    case SPOT_PLAYER       = 'spot_player';
    case SKYROOM           = 'skyroom';
    case NILIROOM          = 'niliroom';
    case SMS_IPPANEL       = 'sms.ippanel';
    case SMS_NOTIFICATIONS = 'sms_notifications';

    case MELLAT        = 'payment.mellat';
    case WALLET        = 'payment.wallet';
    case BANK_TRANSFER = 'payment.bank_transfer';
    case DIGIPAY       = 'payment.digipay';

    /**
     * Secret sub-fields for each setting key.
     *
     * This is the single registry for secret handling: every key named here is
     * encrypted when written through SettingsService, decrypted on read, redacted
     * in responses and audit logs via SettingSecretRedactor, and excluded from the
     * admin settings media skip list.
     *
     * @return list<string>
     */
    public function secretFields(): array
    {
        return match ($this) {
            self::IMS             => ['api_key'],
            self::MOODLE          => ['token', 'auth_userkey_token'],
            self::BIG_BLUE_BUTTON => ['secret', 'default_attendee_password', 'default_moderator_password'],
            self::SPOT_PLAYER     => ['api_key'],
            self::SKYROOM         => ['api_key', 'secret'],
            self::NILIROOM        => ['api_token'],
            self::SMS_IPPANEL     => ['api_key'],
            self::MELLAT          => ['password'],
            self::DIGIPAY         => ['client_secret', 'password'],
            default               => [],
        };
    }

    /**
     * Whether this key carries any encrypted secret fields.
     */
    public function hasSecrets(): bool
    {
        return $this->secretFields() !== [];
    }
}
