<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Product\ProductableEnum;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class LearningPathProductableExistRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^steps\.(\d+)\.productable_id$/', $attribute, $matches)) {
            return;
        }

        $type = ProductableEnum::tryFrom((string) data_get(
            $this->data,
            sprintf('steps.%d.productable_type', (int) $matches[1]),
        ));

        if ($type === null || $type === ProductableEnum::BUNDLE) {
            $fail('The selected productable reference is invalid.');

            return;
        }

        if ($type->getModelClass()::query()->whereKey($value)->doesntExist()) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
