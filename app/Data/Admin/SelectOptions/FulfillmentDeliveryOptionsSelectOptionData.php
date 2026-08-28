<?php

declare(strict_types=1);

namespace App\Data\Admin\SelectOptions;

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class FulfillmentDeliveryOptionsSelectOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public Collection $delivery_options,
    ) {}

    public static function fromFulfillmentType(FulfillmentTypeEnum $fulfillmentType): self
    {
        return new self(
            value: $fulfillmentType->value,
            label: $fulfillmentType->translate(),
            delivery_options: collect(
                array_map(
                    static fn (DeliveryMethodEnum $deliveryMethod): DeliveryOptionSelectOptionData => new DeliveryOptionSelectOptionData(
                        value: $deliveryMethod->value,
                        label: $deliveryMethod->translate(),
                    ),
                    $fulfillmentType->getDeliveryMethods(),
                ),
            ),
        );
    }
}
