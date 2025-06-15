<?php

namespace App\Data\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use Spatie\LaravelData\Data;

class EmptyDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
    )
    {
    }
}
