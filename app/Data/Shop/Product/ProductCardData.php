<?php

declare(strict_types=1);

namespace App\Data\Shop\Product;

use App\Data\Shop\ProductPriceData;
use App\Data\Shop\Teacher\TeacherListData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\MediaTagEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\Product\ProductDeliveryStatusEnum;
use App\Enums\Product\ProductRegistrationStatusEnum;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Product;
use App\Models\Seminar;
use Carbon\CarbonImmutable;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
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
        public ?string $event_start_at = null,
        public ?string $event_ended_at = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $event_start_datetime = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d H:i:s')]
        public ?Verta $event_end_datetime = null,
        public ?int $duration = null,
        public ?int $duration_seconds = null,
        public ?int $page_count = null,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public ?CourseDifficultyLevelEnum $difficulty_level = null,
        public ?string $version = null,
        public ?string $file_type = null,
        public ?string $file_size = null,
        public ?int $size_bytes = null,
    ) {}

    public static function fromModel(
        Product $product,
        ProductPriceData $priceData,
        bool $withFullPriceData = true
    ): self {
        $productable        = $product->productable;
        $defaultTeacherInfo = isset($productable?->default_teacher_info)
            ? $productable->default_teacher_info
            : null;
        $delivery         = self::deliveryPresentation($product, $defaultTeacherInfo);
        $typePresentation = self::typePresentation($productable);

        return new self(
            slug: $product->slug,
            name: $product->name,
            excerpt: $product->short_description,
            price: $priceData->min_price,
            original_price: $priceData->min_original_price,
            price_range: $priceData->range,
            has_discount: $priceData->has_discount,
            discount_percent: $priceData->discount_percentage,
            is_free: ($priceData->min_price ?? 0) <= 0,
            is_featured: $product->is_featured,
            provides_certificate: $productable?->provides_certificate ?? false,
            product_type: ProductableEnum::from($product->productable_type),
            thumbnail_url: $productable?->thumbnail_url,
            available_from: $delivery['available_from'] ? Verta::instance($delivery['available_from']) : null,
            available_to: $delivery['available_to'] ? Verta::instance($delivery['available_to']) : null,
            registration_start_date: $delivery['registration_start_date']
                ? Verta::instance($delivery['registration_start_date'])
                : null,
            registration_end_date: $delivery['registration_end_date']
                ? Verta::instance($delivery['registration_end_date'])
                : null,
            teachers: $delivery['teachers'],
            reviews_count: $productable->review_count ?? 0,
            average_rating: (float) ($productable->average_rating ?? 0.0),
            registration_status: $delivery['registration_status'],
            delivery_type: $delivery['delivery_type'],
            price_data: $withFullPriceData ? $priceData : null,
            event_start_at: $product->event_start_at?->toDateString(),
            event_ended_at: $product->event_ended_at?->toDateString(),
            event_start_datetime: $product->event_start_at ? Verta::instance($product->event_start_at) : null,
            event_end_datetime: $product->event_ended_at ? Verta::instance($product->event_ended_at) : null,
            duration: $typePresentation['duration'],
            duration_seconds: $typePresentation['duration_seconds'],
            page_count: $typePresentation['page_count'],
            difficulty_level: $typePresentation['difficulty_level'],
            version: $typePresentation['version'],
            file_type: $typePresentation['file_type'],
            file_size: $typePresentation['file_size'],
            size_bytes: $typePresentation['size_bytes'],
        );
    }

    /**
     * Build the flat ProductCardData shape from a Course and its commercial Product shell.
     *
     * Course fields provide the catalog identity and presentation; Product fields provide
     * pricing, featured state, delivery dates, registration state, and fulfillment information.
     */
    public static function fromCourse(
        Course $course,
        ?Product $product,
        ?ProductPriceData $priceData,
        bool $withFullPriceData = true,
    ): self {
        $delivery = [
            'available_from'          => null,
            'available_to'            => null,
            'registration_start_date' => null,
            'registration_end_date'   => null,
            'teachers'                => $course->default_teacher_info !== null
                ? [$course->default_teacher_info]
                : [],
            'registration_status' => null,
            'delivery_type'       => null,
        ];

        if ($product !== null) {
            $delivery = self::deliveryPresentation($product, $course->default_teacher_info);
        }

        return new self(
            slug: $product ? $product->slug : $course->slug,
            name: $course->full_name,
            excerpt: $course->description,
            price: $priceData?->min_price,
            original_price: $priceData?->min_original_price,
            price_range: $priceData?->range,
            has_discount: $priceData?->has_discount,
            discount_percent: $priceData?->discount_percentage,
            is_free: $priceData !== null && ($priceData->min_price ?? 0) <= 0,
            is_featured: $product?->is_featured ?? false,
            provides_certificate: (bool) $course->provides_certificate,
            product_type: ProductableEnum::COURSE,
            thumbnail_url: $course->thumbnail_url,
            available_from: $delivery['available_from'] ? Verta::instance($delivery['available_from']) : null,
            available_to: $delivery['available_to'] ? Verta::instance($delivery['available_to']) : null,
            registration_start_date: $delivery['registration_start_date']
                ? Verta::instance($delivery['registration_start_date'])
                : null,
            registration_end_date: $delivery['registration_end_date']
                ? Verta::instance($delivery['registration_end_date'])
                : null,
            teachers: $delivery['teachers'],
            reviews_count: (int) $course->review_count,
            average_rating: (float) $course->average_rating,
            registration_status: $delivery['registration_status'],
            delivery_type: $delivery['delivery_type'],
            price_data: $withFullPriceData ? $priceData : null,
            event_start_at: $product?->event_start_at?->toDateString(),
            event_ended_at: $product?->event_ended_at?->toDateString(),
            event_start_datetime: $product?->event_start_at ? Verta::instance($product->event_start_at) : null,
            event_end_datetime: $product?->event_ended_at ? Verta::instance($product->event_ended_at) : null,
            duration: $course->duration,
            difficulty_level: $course->difficulty_level,
        );
    }

    /** @return array{duration: ?int, duration_seconds: ?int, page_count: ?int, difficulty_level: ?CourseDifficultyLevelEnum, version: ?string, file_type: ?string, file_size: ?string, size_bytes: ?int} */
    private static function typePresentation(mixed $productable): array
    {
        $presentation = [
            'duration'         => null,
            'duration_seconds' => null,
            'page_count'       => null,
            'difficulty_level' => null,
            'version'          => null,
            'file_type'        => null,
            'file_size'        => null,
            'size_bytes'       => null,
        ];

        if ($productable instanceof Course) {
            $presentation['duration']         = $productable->duration;
            $presentation['difficulty_level'] = $productable->difficulty_level;
        } elseif ($productable instanceof Seminar) {
            $presentation['difficulty_level'] = $productable->difficulty_level;
        } elseif ($productable instanceof DigitalAsset) {
            $presentation['duration_seconds'] = $productable->duration_seconds;
            $presentation['page_count']       = $productable->page_count;
            $presentation['difficulty_level'] = $productable->difficulty_level;
            $presentation['version']          = $productable->version;

            if ($productable->relationLoaded('media')) {
                $media                      = $productable->getMedia(MediaTagEnum::MAIN->value)->first();
                $sizeBytes                  = $media?->size      !== null ? (int) $media->size : null;
                $presentation['file_type']  = $media?->extension !== null ? mb_strtoupper($media->extension) : null;
                $presentation['file_size']  = formatFileSize($sizeBytes);
                $presentation['size_bytes'] = $sizeBytes;
            }
        }

        return $presentation;
    }

    /**
     * @return array{
     *     available_from: null|Carbon|CarbonImmutable,
     *     available_to: null|Carbon|CarbonImmutable,
     *     registration_start_date: null|Carbon|CarbonImmutable,
     *     registration_end_date: null|Carbon|CarbonImmutable,
     *     teachers: array<int, mixed>,
     *     registration_status: ?ProductRegistrationStatusEnum,
     *     delivery_type: ?ProductDeliveryStatusEnum
     * }
     */
    private static function deliveryPresentation(Product $product, mixed $defaultTeacherInfo): array
    {
        $availableFrom         = null;
        $availableTo           = null;
        $registrationStartDate = null;
        $registrationEndDate   = null;
        $teacherMap            = [];
        $fulfillmentTypes      = [];

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

        $teachers = array_values($teacherMap);
        if (! $teachers && $defaultTeacherInfo !== null) {
            $teachers = [$defaultTeacherInfo];
        }

        $fulfillmentTypes = array_unique($fulfillmentTypes);
        $deliveryStatus   = match (true) {
            count($fulfillmentTypes) > 1 => ProductDeliveryStatusEnum::COMBINED,
            in_array(FulfillmentTypeEnum::ONLINE_SERVICE->value, $fulfillmentTypes),
            in_array(FulfillmentTypeEnum::OFFLINE_SERVICE->value, $fulfillmentTypes)   => ProductDeliveryStatusEnum::ONLINE,
            in_array(FulfillmentTypeEnum::IN_PERSON_SERVICE->value, $fulfillmentTypes) => ProductDeliveryStatusEnum::IN_PERSON,
            default                                                                    => null,
        };

        $registrationStatus = self::isInProgress($registrationStartDate, $registrationEndDate)
            ? ProductRegistrationStatusEnum::IN_PROGRESS
            : null;
        if ($availableTo && now()->isAfter($availableTo)) {
            $registrationStatus = ProductRegistrationStatusEnum::FINISHED;
        }

        return [
            'available_from'          => $availableFrom,
            'available_to'            => $availableTo,
            'registration_start_date' => $registrationStartDate,
            'registration_end_date'   => $registrationEndDate,
            'teachers'                => $teachers,
            'registration_status'     => $registrationStatus,
            'delivery_type'           => $deliveryStatus,
        ];
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
