<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;

final class InPersonDetailsData extends BaseDeliveryOptionDetailData implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public string $address,
        public ?string $map_url,
        public ?string $additional_info,
        #[Nullable, IntegerType]
        public ?int $moodle_quiz_course_id = null,
    ) {}
}
