<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class ProductableExistRule implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!data_get($this->data, 'productable_type')){
            return;
        }
        $type = ProductableEnum::tryFrom(data_get($this->data, 'productable_type'));
        if (! $type) {
            return;
        }

        $model = $type->getModelClass();
        if ($model::query()->where('id', $value)->doesntExist()) {
            $fail(__('validation.exists', ['attribute' => __('validation.attributes.product.productable_type')]));
        }
        if (
            data_get($this->data, 'force_create')
            || data_get($this->data, 'status') !== PublicationStatusEnum::PUBLISHED->value
        ) {
            return;
        }
        $existingProduct = Product::query()
            ->where('productable_id', $value)
            ->where('productable_type', $type->value)
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->first();

        if ($existingProduct) {
            $fail(__('validation.productable_exist', [
                'type' => $type->translate(),
                'name' => $existingProduct->name,
            ]));
        }
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
}
