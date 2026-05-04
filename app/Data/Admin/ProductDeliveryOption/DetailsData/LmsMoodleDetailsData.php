<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

final class LmsMoodleDetailsData extends Data implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public int $moodle_course_id,
        public ?int $activity_id,
        #[WithCast(AdvancedDateTimeInterfaceCast::class)]
        public ?Verta $enrollment_start_date,
        #[WithCast(AdvancedDateTimeInterfaceCast::class)]
        public ?Verta $enrollment_end_date,
    ) {}
}
