<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class LiveSessionSkyroomDetailsData extends BaseDeliveryOptionDetailData implements DeliveryOptionDetailDataContract
{
    public function __construct(
        #[Nullable, IntegerType]
        public ?int $room_id,

        #[Nullable, IntegerType] // In minutes
        public ?int $planned_duration_minutes, // Admin sets the expected duration for this specific session

        #[Nullable, StringType, Max(2000)]
        public ?string $admin_notes, // Internal notes for this specific session setup

        #[Nullable, IntegerType]
        public ?int $moodle_quiz_course_id = null,
    ) {}
}
