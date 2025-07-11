<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\FulfillmentTypeEnum;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class ProductDeliveryOptionCheckRule implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! data_get($this->data, 'fulfillment_type')) {
            return;
        }
        $fullfillmentType = FulfillmentTypeEnum::tryFrom(data_get($this->data, 'fulfillment_type'));
        if (! $fullfillmentType || ! $value) {
            return;
        }
        if ($fullfillmentType->hasDeliveryMethod($value)) {
            return;
        }

        $fail(__('validation.custom.product_delivery_option_check_rule.invalid_delivery_method', [
            'delivery_method'  => $value,
            'fulfillment_type' => $fullfillmentType->value,
        ]));
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
