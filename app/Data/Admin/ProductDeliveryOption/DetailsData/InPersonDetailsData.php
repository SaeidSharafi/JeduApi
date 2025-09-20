<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use Spatie\LaravelData\Data;

final class InPersonDetailsData extends Data implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public string $location,
        public string $duration,
        public string $schedule,
        public ?string $additional_info,
    ) {}
}
