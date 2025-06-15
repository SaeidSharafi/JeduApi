<?php

namespace App\Data\Casts;

use App\Contracts\DeliveryOptionDetialDataContract;
use App\Data\ProductDeliveryOption\DetailsData\DirectDownloadDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\EmptyDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\InPersonDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\LiveSessionBbbDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\LiveSessionSkyroomDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\LmsMoodleDetailsData;
use App\Data\ProductDeliveryOption\DetailsData\VideoPlatformSpotplayerDetailsData;
use App\Enums\DeliveryMethodEnum;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class DeliveryOptionDetailCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): DeliveryOptionDetialDataContract
    {
        $fulfillmentType = $properties['fulfillment_type'] ?? null;
        $deliveryMethod = $properties['delivery_method'] ?? null;

        if (!$fulfillmentType || !$deliveryMethod) {
            return EmptyDetailsData::from([]);
        }

        if (!$value) {
            return EmptyDetailsData::from([]);
        }

        return match ($deliveryMethod) {
            DeliveryMethodEnum::LMS_MOODLE => LmsMoodleDetailsData::from($value),
            DeliveryMethodEnum::DIRECT_DOWNLOAD => DirectDownloadDetailsData::from($value),
            DeliveryMethodEnum::IN_PERSON => InPersonDetailsData::from($value),
            DeliveryMethodEnum::LIVE_SESSION_BBB => LiveSessionBbbDetailsData::from($value),
            DeliveryMethodEnum::LIVE_SESSION_SKYROOM => LiveSessionSkyroomDetailsData::from($value),
            DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER => VideoPlatformSpotplayerDetailsData::from($value),
            default => EmptyDetailsData::from([]),
        };

    }
}
