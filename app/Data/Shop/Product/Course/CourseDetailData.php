<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use App\Data\Shop\Product\CategoryCardData;
use App\Data\Shop\Product\ProductDeliveryOptionData;
use App\Data\Shop\ProductPriceData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Models\Product;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class CourseDetailData extends Data
{
    public function __construct(
        public string $slug,
        public string $full_name,
        public string $short_name,
        public ?ProductPriceData $priceData,
        public ?string $description,
        public ?int $duration,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public CourseDifficultyLevelEnum $difficulty_level,
        public ?string $career_prospects_text,
        public ?string $curriculum_summary_text,
        public ?array $outcomes_json,
        public ?string $default_teacher_info,
        public ?array $additional_info,
        public ?string $meta_title,
        public ?string $meta_description,
        public ?string $meta_keywords,
        public ?array $properties,
        public ?array $details,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public PublicationStatusEnum $status,
        #[DataCollectionOf(CategoryCardData::class)]
        public ?Collection $categories,
        #[DataCollectionOf(ProductDeliveryOptionData::class)]
        public ?Collection $delivery_options = null,
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
                    $pdo = $product->productDeliveryOptions->firstWhere('uuid', $pdoPrice->uuid);

                    return new ProductDeliveryOptionData(
                        uuid: $pdo->uuid,
                        sku: $pdo->sku,
                        name: $pdo->name,
                        price_data: $pdoPrice,
                        fulfillment_type: $pdo->fulfillment_type,
                        delivery_method: $pdo->delivery_method,
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
                'slug'                    => $product->slug,
                'full_name'               => $product->name ?? $product->productable->full_name,
                'short_name'              => $product->short_name,
                'priceData'               => $courseDetailPriceData,
                'description'             => $product->productable->description,
                'duration'                => $product->productable->duration,
                'difficulty_level'        => $product->productable->difficulty_level,
                'career_prospects_text'   => $product->productable->career_prospects_text,
                'curriculum_summary_text' => $product->productable->curriculum_summary_text,
                'outcomes_json'           => $product->productable->outcomes_json,
                'default_teacher_info'    => $product->productable->default_teacher_info,
                'additional_info'         => $product->productable->additional_info,
                'meta_title'              => $product->productable->meta_title,
                'meta_description'        => $product->productable->meta_description,
                'meta_keywords'           => $product->productable->meta_keywords,
                'properties'              => $product->productable->properties,
                'details'                 => $product->details_json,
                'status'                  => $product->status,
                'categories'              => $product->categories,
                'delivery_options'        => $pdoData,
                'media'                   => $product->productable->getAllMedia(urlOnly: true),
            ]
        );
    }
}
