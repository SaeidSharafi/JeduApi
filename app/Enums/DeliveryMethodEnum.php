<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum DeliveryMethodEnum: string
{
    use AdvanceEnum;

    case LMS_MOODLE = 'lms_moodle';
    case DIRECT_DOWNLOAD = 'direct_download';
    case VIDEO_PLATFORM_SPOTPLAYER = 'video_platform_spotplayer';
    case IN_PERSON = 'in_person';
    case LIVE_SESSION_BBB = 'live_session_bbb';
    case LIVE_SESSION_SKYROOM = 'live_session_skyroom';
}
