<?php

namespace App\Data\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use Spatie\LaravelData\Data;

class VideoPlatformSpotplayerDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
        public string $course_id,
    )
    {
    }
}
