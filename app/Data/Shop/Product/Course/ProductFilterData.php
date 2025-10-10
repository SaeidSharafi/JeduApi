<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductFilterData extends Data
{
    public function __construct(
        public ?array $category_slugs = null, // Use IDs for global search
        public ?string $type = null,
        public ?array $fulfillment_types = [],
        public ?string $difficulty_level = null,
        public ?int $min_price = null,
        public ?int $max_price = null,
        public ?bool $with_discounts = null,
    ) {
    }

    public static function rules(?ValidationContext $context = null, string $prefix = ''): array
    {
        return [
            $prefix.'category_slugs'      => ['sometimes', 'array'],
            $prefix.'category_slugs.*'    => ['string'],
            $prefix.'type'                => ['sometimes', 'string', Rule::enum(ProductableEnum::class)],
            $prefix.'fulfillment_types'   => ['sometimes', 'array'],
            $prefix.'fulfillment_types.*' => ['string', Rule::enum(FulfillmentTypeEnum::class)],
            $prefix.'difficulty_level'    => ['sometimes', 'string', Rule::enum(CourseDifficultyLevelEnum::class)],
            $prefix.'min_price'           => ['sometimes', 'integer', 'min:0'],
            $prefix.'max_price'           => ['sometimes', 'integer', "gt:{$prefix}min_price"],
            $prefix.'with_discounts'      => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function queryParameters(string $prefix = ''): array
    {
        return [
            $prefix.'category_ids'       => [
                'description' => 'Filter by category IDs',
                'example'     => [1, 2, 3],
            ],
            $prefix.'type'               => [
                'description' => 'Filter by product type (e.g., course, ebook)',
                'example'     => ProductableEnum::COURSE->value,
            ],
            $prefix.'fulfillment_types'  => [
                'description' => 'Filter by fulfillment types (e.g., online, offline)',
                'example'     => [
                    FulfillmentTypeEnum::ONLINE_SERVICE->value, FulfillmentTypeEnum::OFFLINE_SERVICE->value
                ],
            ],
            $prefix.'fulfillment_type.*' => [
                'description' => 'fulfillment type',
                'example'     => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ],

            $prefix.'difficulty_level' => [
                'description' => 'Filter by course difficulty level (e.g., beginner, intermediate, advanced)',
                'example'     => CourseDifficultyLevelEnum::BEGINNER->value,

            ],
            $prefix.'min_price'        => [
                'description' => 'Only include products with a minimum price greater than or equal to this amount.',
                'example'     => 100_000,
            ],
            $prefix.'max_price'        => [
                'description' => 'Only include products with a maximum price less than or equal to this amount.',
                'example'     => 500_000,
            ],
            $prefix.'with_discounts'   => [
                'description' => 'When true, only include products that currently have an active discount.',
                'example'     => true,
            ],
        ];
    }
}
