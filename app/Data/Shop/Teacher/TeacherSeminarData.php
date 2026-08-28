<?php

declare(strict_types=1);

namespace App\Data\Shop\Teacher;

use App\Models\ProductDeliveryOption;
use Spatie\LaravelData\Data;

final class TeacherSeminarData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $short_name,
        public string $description,
    ) {}

    public static function fromModel(ProductDeliveryOption $deliveryOption): self
    {
        return new self(
            $deliveryOption->uuid,
            $deliveryOption->product->name,
            $deliveryOption->product->short_name,
            $deliveryOption->product->short_description,
        );
    }
}
