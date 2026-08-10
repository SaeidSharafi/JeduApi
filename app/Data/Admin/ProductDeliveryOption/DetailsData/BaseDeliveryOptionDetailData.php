<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

abstract class BaseDeliveryOptionDetailData extends Data implements DeliveryOptionDetailDataContract
{
    public ?string $lms_course_code = null;

    #[WithCast(AdvancedDateTimeInterfaceCast::class), WithTransformer(AdvancedDateTimeInterfaceTransformer::class, 'Y-m-d')]
    public ?Verta $start_date = null;

    public ?array $schedule_days = null;

    public ?int $duration = null;
}
