<?php

declare(strict_types=1);

namespace App\Data\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use Spatie\LaravelData\Data;

final class InPersonDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
        public string $location,
        public string $duration,
        public string $schedule,
        public ?string $additional_info,
    ) {}
}
