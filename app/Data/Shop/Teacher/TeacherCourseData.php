<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use App\Data\Shop\Product\ProductDeliveryOptionCardData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\PublicationStatusEnum;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class TeacherCourseData extends Data
{
    public function __construct(
        public string $uuid,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_end_date,
        public ?string $external_enrollment_id,
        public ?string $notes,
        public ?array $provisioning_data,
        public ProductDeliveryOption $productDeliveryOption,
        public OrderItem $orderItem,
        public bool $is_virtual = false,
        public ?ProductDeliveryOptionCardData $product = null,
    ) {
        $this->product    = ProductDeliveryOptionCardData::fromModel($productDeliveryOption);
        $this->is_virtual = $productDeliveryOption->delivery_method->isVirtual();
    }

    protected function exceptProperties(): array
    {
        return [
            'productDeliveryOption',
            'orderItem',
        ];
    }
}
