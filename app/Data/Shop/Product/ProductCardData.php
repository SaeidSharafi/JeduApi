<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Data\Shop\ProductPriceData;
use App\Data\Shop\Teacher\TeacherListData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\Product\ProductDeliveryStatusEnum;
use App\Enums\Product\ProductRegistrationStatusEnum;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class ProductCardData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $excerpt,
        public ?int $price,
        public ?int $original_price,
        public ?array $price_range,
        public ?bool $has_discount,
        public ?float $discount_percent,
        public bool $is_free,
        public bool $is_featured,
        public bool $provides_certificate,
        #[WithTransformer(TranslatableEnumData::class)]
        public ProductableEnum $product_type,
        public ?string $thumbnail_url,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $available_from,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $available_to,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $registration_start_date,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?Verta $registration_end_date,
        public ?array $teachers,
        public ?int $reviews_count,
        public ?float $average_rating,
        #[WithTransformer(TranslatableEnumData::class)]
        public ?ProductRegistrationStatusEnum $registration_status,
        #[WithTransformer(TranslatableEnumData::class)]
        public ?ProductDeliveryStatusEnum $delivery_type,
        public ?ProductPriceData $price_data = null,
    ) {}

    public static function fromModel(
        Product $product,
        ProductPriceData $priceData,
        bool $withFullPriceData = true
    ): self {
        $teachers              = [];
        $availableFrom         = null;
        $availableTo           = null;
        $registrationStartDate = null;
        $registrationEndDate   = null;
        $teacherMap            = [];
        $registrationStatus    = null;
        $fulfillmentTypes      = [];

        // single pass: collect date boundaries + unique teachers
        foreach ($product->productDeliveryOptions as $option) {
            $fulfillmentTypes[] = $option?->fulfillment_type?->value;
            if ($option->available_from) {
                $availableFrom = is_null($availableFrom)
                    ? $option->available_from
                    : min($availableFrom, $option->available_from);
            }
            if ($option->available_to) {
                $availableTo = is_null($availableTo)
                    ? $option->available_to
                    : max($availableTo, $option->available_to);
            }
            if ($option->registration_start_date) {
                $registrationStartDate = is_null($registrationStartDate)
                    ? $option->registration_start_date
                    : min($registrationStartDate, $option->registration_start_date);
            }
            if ($option->registration_end_date) {
                $registrationEndDate = is_null($registrationEndDate)
                    ? $option->registration_end_date
                    : max($registrationEndDate, $option->registration_end_date);
            }
            foreach ($option->teachers as $teacher) {
                $key = is_array($teacher) ? $teacher['id'] : $teacher->id;
                if (! isset($teacherMap[$key])) {
                    $teacherMap[$key] = TeacherListData::from($teacher);
                }
            }
        }
        if (self::isInProgress($registrationStartDate, $registrationEndDate)) {
            $registrationStatus = ProductRegistrationStatusEnum::IN_PROGRESS;
        }
        if ($availableTo && now()->isAfter($availableTo)) {
            $registrationStatus = ProductRegistrationStatusEnum::FINISHED;
        }
        $teachers = array_values($teacherMap);
        if (! $teachers) {
            $teachers = isset($product->productable?->default_teacher_info)
                ? [$product->productable?->default_teacher_info] : [];
        }
        $fulfillmentTypes = array_unique($fulfillmentTypes);
        $deliveryStatus   = match (true) {
            count($fulfillmentTypes) > 1 => ProductDeliveryStatusEnum::COMBINED,
            in_array(FulfillmentTypeEnum::ONLINE_SERVICE->value, $fulfillmentTypes),
            in_array(FulfillmentTypeEnum::OFFLINE_SERVICE->value, $fulfillmentTypes)   => ProductDeliveryStatusEnum::ONLINE,
            in_array(FulfillmentTypeEnum::IN_PERSON_SERVICE->value, $fulfillmentTypes) => ProductDeliveryStatusEnum::IN_PERSON,
            default                                                                    => null,
        };

        return new self(
            slug: $product->slug,
            name: $product->name,
            excerpt: $product->short_description,
            price: $priceData->min_price,
            original_price: $priceData->min_original_price,
            price_range: $priceData->range,
            has_discount: $priceData->has_discount,
            discount_percent: $priceData->discount_percentage,
            is_free: ($priceData?->min_price ?? 0) <= 0,
            is_featured: $product->is_featured,
            provides_certificate: $product->productable->provides_certificate ?? false,
            product_type: ProductableEnum::from($product->productable_type),
            thumbnail_url: $product->productable->thumbnail_url ?? null,
            available_from: $availableFrom ? Verta::instance($availableFrom) : null,
            available_to: $availableTo ? Verta::instance($availableTo) : null,
            registration_start_date: $registrationStartDate ? Verta::instance($registrationStartDate) : null,
            registration_end_date: $registrationEndDate ? Verta::instance($registrationEndDate) : null,
            teachers: $teachers,
            reviews_count: $product->reviews_count   ?? 0,
            average_rating: $product->average_rating ?? 0.0,
            registration_status: $registrationStatus,
            delivery_type: $deliveryStatus,
            price_data: $withFullPriceData ? $priceData : null,
        );
    }

    private static function isInProgress(null|Carbon|CarbonImmutable $registrationStartDate, null|Carbon|CarbonImmutable $registrationEndDate): bool
    {
        if (is_null($registrationStartDate) && is_null($registrationEndDate)) {
            return true;
        }

        if (is_null($registrationStartDate) && $registrationEndDate && now()->lessThanOrEqualTo($registrationEndDate)) {
            return true;
        }
        if ($registrationStartDate && now()->greaterThanOrEqualTo($registrationStartDate) && is_null($registrationEndDate)) {
            return true;
        }
        if ($registrationStartDate && now()->greaterThanOrEqualTo($registrationStartDate) && $registrationEndDate && now()->lessThanOrEqualTo($registrationEndDate)) {
            return true;
        }

        return false;
    }
}
