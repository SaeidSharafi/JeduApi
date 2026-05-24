<?php

declare(strict_types=1);

namespace App\Enums\System;

enum SettingKeyEnum: string
{
    case ABOUT_US         = 'about_us';
    case CONTACT_INFO     = 'contact_info';
    case COLLABORATION    = 'collaboration';
    case HEADER           = 'header';
    case FOOTER           = 'footer';
    case RULES            = 'rules';
    case SLIDERS          = 'sliders';
    case HOME_PAGE_BLOCKS = 'home_page_blocks';
    case IMS              = 'ims';
    case MOODLE           = 'moodle';
    case BIG_BLUE_BUTTON  = 'big_blue_button';
    case SPOT_PLAYER      = 'spot_player';

    /**
     * Secret sub-fields that must be encrypted at rest for each integration key.
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
            default               => [],
        };
    }
}
