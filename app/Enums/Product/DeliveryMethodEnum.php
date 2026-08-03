<?php

declare(strict_types=1);

namespace App\Enums\Product;

use App\Data\Admin\ProductDeliveryOption\DetailsData\DirectDownloadDetailsData;
use App\Data\Admin\ProductDeliveryOption\DetailsData\InPersonDetailsData;
use App\Data\Admin\ProductDeliveryOption\DetailsData\LiveSessionBbbDetailsData;
use App\Data\Admin\ProductDeliveryOption\DetailsData\LiveSessionSkyroomDetailsData;
use App\Data\Admin\ProductDeliveryOption\DetailsData\LmsMoodleDetailsData;
use App\Data\Admin\ProductDeliveryOption\DetailsData\VideoPlatformSpotplayerDetailsData;
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

    public function getFulfillmentType(): FulfillmentTypeEnum
    {
        return match ($this) {
            self::LMS_MOODLE,
            self::LIVE_SESSION_BBB,
            self::VIDEO_PLATFORM_SPOTPLAYER,
            self::LIVE_SESSION_SKYROOM => FulfillmentTypeEnum::ONLINE_SERVICE,
            self::DIRECT_DOWNLOAD      => FulfillmentTypeEnum::DIGITAL,
            self::IN_PERSON            => FulfillmentTypeEnum::IN_PERSON_SERVICE,
        };
    }

    public function isVirtual(): bool
    {
        return match ($this) {
            self::LMS_MOODLE,
            self::LIVE_SESSION_BBB,
            self::VIDEO_PLATFORM_SPOTPLAYER,
            self::LIVE_SESSION_SKYROOM             => true,
            self::DIRECT_DOWNLOAD, self::IN_PERSON => false,
        };
    }

    public static function getSeminars(bool $asString = false): array
    {
        if ($asString){
            return [
                self::LIVE_SESSION_BBB->value,
                self::LIVE_SESSION_SKYROOM->value,
            ];
        }
        return [
            self::LIVE_SESSION_BBB,
            self::LIVE_SESSION_SKYROOM,
        ];
    }
}
