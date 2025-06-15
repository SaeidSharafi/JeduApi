<?php

namespace App\Rules;

use App\Enums\FulfillmentTypeEnum;
use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

class ProductDeliveryOptionCheckRule implements DataAwareRule, ValidationRule
{

    protected array $data = [];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $fullfillmentType = FulfillmentTypeEnum::tryFrom(data_get($this->data, 'fulfillment_type'));
        if (!$fullfillmentType || !$value) {
            return;
        }
        if ($fullfillmentType->hasDeliveryMethod($value)){
            return;
        }

        $fail(__('validation.custom.product_delivery_option_check_rule.invalid_delivery_method', [
            'delivery_method' => $value,
            'fulfillment_type' => $fullfillmentType->value,
        ]));
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }
}
