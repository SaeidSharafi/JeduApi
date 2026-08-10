<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;

final class DirectDownloadDetailsData extends BaseDeliveryOptionDetailData implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public int $max_downloads,
    ) {}
}
