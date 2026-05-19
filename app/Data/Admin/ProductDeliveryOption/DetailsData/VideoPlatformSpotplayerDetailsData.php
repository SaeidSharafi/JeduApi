<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class VideoPlatformSpotplayerDetailsData extends Data implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public string $spot_id,
        #[WithCast(AdvancedDateTimeInterfaceCast::class)]
        public ?Verta $updated_at,
    ) {}
}
