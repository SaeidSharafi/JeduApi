<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class DirectDownloadDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
        public int $max_downloads,
        #[WithCast(AdvancedDateTimeInterfaceCast::class)]
        public ?Verta $expiration_date,
    ) {}
}
