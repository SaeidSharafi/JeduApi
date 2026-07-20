<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class UserNeverPurchasedCategoryData extends Data
{
    public function __construct(
        public array $category_ids
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    public static function rules(): array
    {
        return [
            'category_ids'   => ['required', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
