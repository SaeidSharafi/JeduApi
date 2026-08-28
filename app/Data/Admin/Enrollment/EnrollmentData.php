<?php

declare(strict_types=1);

namespace App\Data\Admin\Enrollment;

use App\Data\Admin\Order\OrderItemData;
use App\Data\Admin\Order\OrderListItemData;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Data\Admin\User\ShowUserData;
use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrollmentStatusEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class EnrollmentData extends Data
{
    public function __construct(
        public string $uuid,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public EnrollmentStatusEnum $enrollment_status,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $access_start_date,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $access_end_date,
        public ProvisioningSummaryData $provisioning_summary,
        public ?string $notes,
        #[WithTransformer(AdvancedDateTimeInterfaceTransformer::class)]
        public ?Verta $survey_completed_at,
        public ?Verta $created_at,
        public ?Verta $updated_at,
        public OrderListItemData $order,
        public OrderItemData $orderItem,
        public ShowUserData $customer,
        public ProductDeliveryOptionShowData $productDeliveryOption,
    ) {}
}
