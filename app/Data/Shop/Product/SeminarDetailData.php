<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Data\Shop\Product\Category\CategoryCardData;
use App\Data\Shop\ProductPriceData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class SeminarDetailData extends Data
{
    public function __construct(
        public string $slug,
        public string $full_name,
        public string $short_name,
        public ?string $subtitle,
        public ?ProductPriceData $price_data,
        public ?string $description,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public CourseDifficultyLevelEnum $difficulty_level,
        public ?string $curriculum_summary_text,
        public ?array $outcomes_json,
        public ?string $target_audience,
        public ?string $prerequisites,
        public ?string $promo_video_external_url,
        public ?string $estimated_duration_desc,
        public bool $provides_certificate,
        public ?array $faq,
        public ?string $keywords,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public ?array $details,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        #[DataCollectionOf(CategoryCardData::class)]
        public ?Collection $categories,
        #[DataCollectionOf(ProductDeliveryOptionData::class)]
        public ?Collection $delivery_options = null,
        public ?string $event_start_at = null,
        public ?string $event_ended_at = null,
        public array $media = [],
    ) {}

    public static function fromModel(Product $product, ProductPriceData $data): self
    {
        $pdoData = null;
        if ($product->productDeliveryOptions->isNotEmpty() && $data->prices !== null) {
            $pdoData = $data->prices
                ->filter(function ($pdoPrice) use ($product) {
                    $pdo = $product->productDeliveryOptions->firstWhere('uuid', $pdoPrice->uuid);

                    return $pdo !== null
                        && $pdo->status === PublicationStatusEnum::PUBLISHED;
                    // Note: Removed capacity check as enrolled_count property needs verification
                })
                ->map(function ($pdoPrice) use ($product) {
                    $pdo           = $product->productDeliveryOptions->firstWhere('uuid', $pdoPrice->uuid);
                    $isAvailable   = self::isAvailable($pdo->available_from, $pdo->available_to);
                    $isPurchasable = $isAvailable && self::isAvailable($pdo->registration_start_date, $pdo->registration_end_date);

                    return new ProductDeliveryOptionData(
                        uuid: $pdo->uuid,
                        sku: $pdo->sku,
                        name: $pdo->name,
                        price_data: $pdoPrice,
                        fulfillment_type: $pdo->fulfillment_type,
                        delivery_method: $pdo->delivery_method,
                        is_available: $isAvailable,
                        is_purchasable: $isPurchasable,
                        available_from: $pdo->available_from,
                        available_to: $pdo->available_to,
                        registration_start_date: $pdo->registration_start_date,
                        registration_end_date: $pdo->registration_end_date,
                    );
                });
        }

        $courseDetailPriceData = new ProductPriceData(
            min_price: $data->min_price,
            min_original_price: $data->min_original_price,
            has_featured_price: $data->has_featured_price,
            has_discount: $data->has_discount,
            has_pre_payment: $data->has_pre_payment,
            discount_type: $data->discount_type,
            discount_percentage: $data->discount_percentage,
            highest_discount_amount: $data->highest_discount_amount,
            range: $data->range,
            prices: null,
        );

        return self::from(
            [
                'slug'                     => $product->slug,
                'full_name'                => $product->name ?? $product->productable->full_name,
                'short_name'               => $product->short_name,
                'subtitle'                 => $product->productable->subtitle,
                'price_data'               => $courseDetailPriceData,
                'description'              => $product->productable->description,
                'difficulty_level'         => $product->productable->difficulty_level,
                'curriculum_summary_text'  => $product->productable->curriculum_summary_text,
                'outcomes_json'            => $product->productable->outcomes_json,
                'target_audience'          => $product->productable->target_audience,
                'prerequisites'            => $product->productable->prerequisites,
                'promo_video_external_url' => $product->productable->promo_video_external_url,
                'estimated_duration_desc'  => $product->productable->estimated_duration_desc,
                'keywords'                 => $product->productable->keywords,
                'provides_certificate'     => $product->productable->provides_certificate,
                'faq'                      => $product->productable->faq,
                'meta_title'               => $product->productable->meta_title,
                'meta_description'         => $product->productable->meta_description,
                'meta_keywords'            => $product->productable->meta_keywords,
                'details'                  => $product->details_json,
                'status'                   => $product->status,
                'categories'               => $product->categories,
                'delivery_options'         => $pdoData,
                'event_start_at'           => $product->event_start_at?->toDateString(),
                'event_ended_at'           => $product->event_ended_at?->toDateString(),
                'media'                    => $product->productable->getAllMedia(urlOnly: true),
            ]
        );
    }

    private static function isAvailable(
        null|Carbon|CarbonImmutable $availableFrom,
        null|Carbon|CarbonImmutable $availableTo
    ): bool {
        if ($availableFrom === null && $availableTo === null) {
            return true;
        }

        if ($availableTo && $availableFrom === null) {
            return now()->lessThanOrEqualTo($availableTo);
        }

        if ($availableFrom && $availableTo === null) {
            return now()->greaterThanOrEqualTo($availableFrom);
        }

        return now()->between($availableFrom, $availableTo);
    }
}
