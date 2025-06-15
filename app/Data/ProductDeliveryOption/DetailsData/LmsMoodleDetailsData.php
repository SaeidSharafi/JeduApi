<?php

namespace App\Data\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetialDataContract;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class LmsMoodleDetailsData extends Data implements DeliveryOptionDetialDataContract
{
    public function __construct(
        public string $course_idnumber,
        public ?int $activity_id,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $enrollment_start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $enrollment_end_date,
    )
    {
    }
}
