<?php

namespace App\Data\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class DirectDownloadDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
        public int $max_downloads,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $expiration_date = null,
    )
    {
    }
}
