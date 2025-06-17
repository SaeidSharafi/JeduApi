<?php

declare(strict_types=1);

namespace App\Enums;

use App\Data\ProductDeliveryOption\DetailsData\DirectDownloadDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\InPersonDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\LiveSessionBbbDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\LiveSessionSkyroomDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\LmsMoodleDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\VideoPlatformSpotplayerDetailsData;
use App\Traits\AdvanceEnum;

enum DeliveryMethodEnum: string
{
    use AdvanceEnum;

    case LMS_MOODLE                = 'lms_moodle';
    case DIRECT_DOWNLOAD           = 'direct_download';
    case VIDEO_PLATFORM_SPOTPLAYER = 'video_platform_spotplayer';
    case IN_PERSON                 = 'in_person';
    case LIVE_SESSION_BBB          = 'live_session_bbb';
    case LIVE_SESSION_SKYROOM      = 'live_session_skyroom';

    public function getDetailsDtoClass(): string
    {
        return match ($this) {
            self::LMS_MOODLE                => LmsMoodleDetailsData::class,
            self::DIRECT_DOWNLOAD           => DirectDownloadDetailsData::class,
            self::VIDEO_PLATFORM_SPOTPLAYER => VideoPlatformSpotplayerDetailsData::class,
            self::IN_PERSON                 => InPersonDetailsData::class,
            self::LIVE_SESSION_BBB          => LiveSessionBbbDetailsData::class,
            self::LIVE_SESSION_SKYROOM      => LiveSessionSkyroomDetailsData::class,
        };
    }
}
