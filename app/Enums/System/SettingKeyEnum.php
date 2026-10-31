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
    case SITE_IDENTITY     = 'site_identity';
    case RULES             = 'rules';
    case SLIDERS           = 'sliders';
    case HOME_PAGE_BLOCKS  = 'home_page_blocks';
    case IMS               = 'ims';
    case MOODLE            = 'moodle';
    case SPOT_PLAYER       = 'spot_player';
    case SKYROOM           = 'skyroom';
    case NILIROOM          = 'niliroom';
    case SMS_IPPANEL       = 'sms.ippanel';
    case SMS_NOTIFICATIONS = 'sms_notifications';

    case SEARCH_ENGINE = 'search.engine';

    case MELLAT        = 'payments.mellat';
    case WALLET        = 'payments.wallet';
    case BANK_TRANSFER = 'payments.bank_transfer';
    case DIGIPAY       = 'payments.digipay';

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
            self::IMS         => ['api_key'],
            self::MOODLE      => ['token', 'auth_userkey_token'],
            self::SPOT_PLAYER => ['api_key'],
            self::SKYROOM     => ['api_key', 'secret'],
            self::NILIROOM    => ['api_token'],
            self::SMS_IPPANEL => ['api_key'],
            self::MELLAT      => ['password'],
            self::DIGIPAY     => ['client_secret', 'password'],
            default           => [],
        };
    }

    /**
     * The storefront cache tags a write to this setting invalidates.
     *
     * The single registry for public-facing settings: a key that is rendered on
     * the storefront names the tags its change disturbs, and every other key
     * (integration credentials, the search-engine choice, payment gateways)
     * names none, so writing one never notifies the storefront.
     *
     * @return list<StorefrontTag>
     */
    public function storefrontTags(): array
    {
        return match ($this) {
            self::HEADER        => [StorefrontTag::SettingsHeader],
            self::FOOTER        => [StorefrontTag::SettingsFooter],
            self::SITE_IDENTITY => [StorefrontTag::SettingsSite],
            self::ABOUT_US      => [StorefrontTag::CmsAboutUs],
            self::CONTACT_INFO  => [StorefrontTag::CmsContactUs],
            self::COLLABORATION => [StorefrontTag::CmsCollaboration],
            default             => [],
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
