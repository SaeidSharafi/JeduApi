<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PublishedProductExistRule implements ValidationRule
{
    public function __construct(private readonly ?Product $product) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->product) {
            $fail(__('messages.exceptions.product_not_set'));

            return;
        }
        if (PublicationStatusEnum::tryFrom($value) !== PublicationStatusEnum::PUBLISHED) {
            return;
        }
        $existingProduct = Product::query()
            ->where('productable_id', $this->product->productable_id)
            ->where('productable_type', $this->product->productable_type)
            ->whereNot('id', $this->product->id)
            ->where('status', PublicationStatusEnum::PUBLISHED)
            ->first();
        if ($existingProduct) {
            $fail(__('messages.exceptions.product_already_exists', ['type' => $this->product->productable_type, 'id' => $existingProduct->id]));
        }
    }
}
