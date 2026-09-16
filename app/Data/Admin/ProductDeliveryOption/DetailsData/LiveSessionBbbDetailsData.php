<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;

/**
 * Details for a `live_session_bbb` delivery option.
 */
final class LiveSessionBbbDetailsData extends BaseDeliveryOptionDetailData implements DeliveryOptionDetailDataContract
{
    public function __construct(
        /** The Niliroom room public ID the session runs in. */
        #[Nullable, StringType, Max(255)]
        public ?string $nili_room_id,

        #[Nullable, StringType, Max(2000)]
        public ?string $admin_notes,

        #[Nullable, IntegerType]
        public ?int $moodle_quiz_course_id = null,
    ) {}
}
