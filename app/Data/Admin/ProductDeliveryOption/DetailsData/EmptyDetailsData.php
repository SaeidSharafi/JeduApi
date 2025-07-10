<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use Spatie\LaravelData\Data;

final class EmptyDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
    ) {}
}
