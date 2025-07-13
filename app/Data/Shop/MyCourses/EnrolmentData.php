<?php

namespace App\Data\Shop\MyCourses;

use App\Data\Shop\Product\ProductCardData;
use App\Data\Shop\Product\ProductData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\EnrolmentStatusEnum;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class EnrolmentData extends Data
{

    //#[Computed]
    //public ProductData $product;

    public function __construct(
        public string $uuid,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public EnrolmentStatusEnum $enrollment_status,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d')]
        public ?Verta $access_end_date,
        public ?string $external_enrollment_id,
        public ?string $notes,
        public ?array $provisioning_data,
        public ProductDeliveryOption $productDeliveryOption,
        public OrderItem $orderItem,
        public ?ProductCardData $product = null,
    ) {
        $this->product = ProductCardData::fromModel($productDeliveryOption->product);
    }

    protected function exceptProperties(): array
    {
        return [
            'productDeliveryOption',
            'orderItem',
        ];
    }
}
