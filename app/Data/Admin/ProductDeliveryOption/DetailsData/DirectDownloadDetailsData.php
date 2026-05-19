<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class DirectDownloadDetailsData extends Data implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public int $max_downloads,
    ) {}
}
