<?php

namespace App\Data\ProductDeliveryOption;

use App\Contracts\DeliveryOptionDetialDataContract;
use App\Data\Casts\DeliveryOptionDetailCast;
use App\Data\Product\ProductData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\DeliveryMethodEnum;
use App\Enums\FulfillmentTypeEnum;
use App\Enums\PublicationStatusEnum;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Optional;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class ProductDeliveryOptionShowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public FulfillmentTypeEnum $fulfillment_type,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public DeliveryMethodEnum $delivery_method,
        public int $price,
        public ?int $capacity = null,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public bool $is_prepayment_available,
        public ?int $prepayment_amount,
        #[MapOutputName('details')]
        #[WithCast(DeliveryOptionDetailCast::class)]
        public DeliveryOptionDetialDataContract $details_json,
        public bool $is_featured,
        public ?int $featured_price,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $featured_price_start_date,
        #[WithCast(DateTimeInterfaceCast::class, 'Y-m-d H:i:s')]
        public ?Verta $featured_price_end_date,
        public Optional|null|ProductData $product,
    ) {
    }
}
