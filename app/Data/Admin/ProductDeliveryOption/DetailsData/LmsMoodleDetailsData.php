<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;

final class LmsMoodleDetailsData extends BaseDeliveryOptionDetailData implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public int $moodle_course_id,
        public ?int $activity_id,
        #[WithCast(AdvancedDateTimeInterfaceCast::class), WithTransformer(AdvancedDateTimeInterfaceTransformer::class, 'Y-m-d')]
        public ?Verta $enrollment_start_date,
        #[WithCast(AdvancedDateTimeInterfaceCast::class), WithTransformer(AdvancedDateTimeInterfaceTransformer::class, 'Y-m-d')]
        public ?Verta $enrollment_end_date,
    ) {}
}
