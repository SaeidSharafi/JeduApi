<?php

namespace App\Enums;

enum SettingKeyEnum: string
{
    case ABOUT_US = 'about_us';
    case CONTACT_INFO = 'contact_info';
    case COLLABORATION = 'collaboration';
    case HEADER = 'header';
    case FOOTER = 'footer';
}
