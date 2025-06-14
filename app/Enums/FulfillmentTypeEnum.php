<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum FulfillmentTypeEnum: string
{
    use AdvanceEnum;
    case DIGITAL = 'digital';
    case PHYSICAL = 'physical';
    case ONLINE_SERVICE = 'online_service';
    case OFFILNE_SERVICE = 'offline_service';
    case IN_PERSON_SERVICE = 'in_person_service';

    public function getDelivieryMethods(): array
    {
        return match ($this) {
            self::DIGITAL => [
                DeliveryMethodEnum::DIRECT_DOWNLOAD,

            ],
            self::PHYSICAL => [],
            self::ONLINE_SERVICE => [
                DeliveryMethodEnum::LIVE_SESSION_BBB,
                DeliveryMethodEnum::LIVE_SESSION_SKYROOM,
                DeliveryMethodEnum::LMS_MOODLE,
            ],
            self::OFFILNE_SERVICE => [
                DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
            ],
            self::IN_PERSON_SERVICE => [
                DeliveryMethodEnum::IN_PERSON,
            ],
        };
    }

    public static function getDeliveryMethodsFor(string $fulfillmentType): array
    {
        $fulfillmentType = FulfillmentTypeEnum::tryFrom($fulfillmentType);
        return $fulfillmentType ? $fulfillmentType->getDelivieryMethods() : [];
    }
}
