<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use Spatie\LaravelData\Data;

final class EmptyDetailsData extends Data implements DeliveryOptionDetailDataContract
{
    public function __construct(
    ) {}
}
