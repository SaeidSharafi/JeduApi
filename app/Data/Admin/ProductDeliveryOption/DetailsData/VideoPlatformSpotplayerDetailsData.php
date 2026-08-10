<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption\DetailsData;

use App\Contracts\DeliveryOptionDetailDataContract;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\WithCast;

final class VideoPlatformSpotplayerDetailsData extends BaseDeliveryOptionDetailData implements DeliveryOptionDetailDataContract
{
    public function __construct(
        public string $spot_id,
        #[WithCast(AdvancedDateTimeInterfaceCast::class)]
        public ?Verta $updated_at,
        #[Nullable, IntegerType]
        public ?int $moodle_quiz_course_id = null,
    ) {}
}
