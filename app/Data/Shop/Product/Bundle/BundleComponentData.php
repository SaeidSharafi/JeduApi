<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Bundle;

use App\Data\Shop\Teacher\TeacherListData;
use App\Models\ProductDeliveryOption;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class BundleComponentData extends Data
{
    public function __construct(
        public string $productable_type,
        public int $productable_id,
        public string $productable_slug,
        public string $productable_name,
        public int $product_id,
        public string $product_slug,
        public string $product_name,
        public string $uuid,
        public string $sku,
        public ?string $name,
        public string $delivery_method,
        public string $fulfillment_type,
        public int $base_price,
        public int $allocation,
        public string $provider_presentation,
        public ?array $schedule_days,
        public ?int $access_days,
        public ?Verta $available_from,
        public ?Verta $available_to,
        public ?Verta $registration_start_date,
        public ?Verta $registration_end_date,
        public ?string $format,
        public bool $is_available,
        public ?Collection $teachers,
    ) {}

    public static function fromModel(ProductDeliveryOption $option, int $allocation, bool $isAvailable): self
    {
        $productable = $option->product->productable;

        return new self(
            productable_type: $option->product->productable_type,
            productable_id: $productable->getKey(),
            productable_slug: (string) $productable->slug,
            productable_name: (string) ($productable->full_name ?? $productable->short_name),
            product_id: $option->product_id,
            product_slug: $option->product->slug,
            product_name: $option->product->name,
            uuid: $option->uuid,
            sku: $option->sku,
            name: $option->name,
            delivery_method: $option->delivery_method->value,
            fulfillment_type: $option->fulfillment_type->value,
            base_price: (int) $option->price,
            allocation: $allocation,
            provider_presentation: match ($option->delivery_method->value) {
                'lms_moodle'                => 'Moodle',
                'video_platform_spotplayer' => 'SpotPlayer',
                'direct_download'           => 'Digital download',
                'live_session_bbb'          => 'Niliroom',
                'live_session_skyroom'      => 'Skyroom',
                'in_person'                 => 'In person',
                default                     => 'Component fulfillment',
            },
            schedule_days: data_get($option->details_json, 'schedule_days'),
            access_days: $option->access_days,
            available_from: $option->available_from ? verta($option->available_from) : null,
            available_to: $option->available_to ? verta($option->available_to) : null,
            registration_start_date: $option->registration_start_date ? verta($option->registration_start_date) : null,
            registration_end_date: $option->registration_end_date ? verta($option->registration_end_date) : null,
            format: data_get($option->details_json, 'format'),
            is_available: $isAvailable,
            teachers: $option->teachers?->map(fn ($teacher): TeacherListData => TeacherListData::from($teacher)),
        );
    }
}
