<?php

declare(strict_types=1);

namespace App\Data\Admin\ProductDeliveryOption;

use App\Contracts\DeliveryOptionDetailDataContract;
use App\Data\Admin\Product\ProductData;
use App\Data\Admin\Teacher\TeacherListItemData;
use App\Data\Casts\DeliveryOptionDetailCast;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class ProductDeliveryOptionShowData extends Data
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
        public ?int $capacity,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        public bool $is_prepayment_available,
        public ?int $prepayment_amount,
        #[MapOutputName('details')]
        #[WithCast(DeliveryOptionDetailCast::class)]
        public DeliveryOptionDetailDataContract $details_json,
        public bool $is_featured,
        public ?int $featured_price,
        public ?Verta $featured_price_start_date,
        public ?Verta $featured_price_end_date,
        #[DataCollectionOf(TeacherListItemData::class)]
        public ?DataCollection $teachers,
        public ?ProductData $product,
        public ?Verta $created_at = null,
        public ?Verta $updated_at = null,
    ) {}
}
