<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductFilterData extends Data
{
    public function __construct(
        public ?array $category_slugs, // Use IDs for global search
        public ?array $fulfillment_types,
        public ?string $difficulty_level,
        public ?int $min_price,
        public ?int $max_price,
        public ?bool $with_discounts,
        public ?bool $is_available_now,
        public ?bool $near_capacity_only,
        public ?float $capacity_threshold,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $registration_starts_after,
        #[WithCast(CarbonFromJalaliString::class, format: 'Y-m-d')]
        public ?Carbon $registration_ends_before,
        #[WithCast(CarbonFromJalaliString::class, format: 'Y-m-d')]
        public ?Carbon $available_from,
        #[WithCast(CarbonFromJalaliString::class, format: 'Y-m-d')]
        public ?Carbon $available_to,

    ) {}

    public static function rules(?ValidationContext $context = null, string $prefix = ''): array
    {
        return [
            $prefix.'category_slugs'      => ['sometimes', 'array'],
            $prefix.'category_slugs.*'    => ['string'],
            $prefix.'fulfillment_types'   => ['sometimes', 'array'],
            $prefix.'fulfillment_types.*' => ['string', Rule::enum(FulfillmentTypeEnum::class)],
            $prefix.'difficulty_level'    => ['sometimes', 'string', Rule::enum(CourseDifficultyLevelEnum::class)],
            $prefix.'min_price'           => ['sometimes', 'integer', 'min:0'],
            $prefix.'max_price'           => ['sometimes', 'integer', "gt:{$prefix}min_price"],
            $prefix.'with_discounts'      => ['sometimes', 'boolean'],
            $prefix.'near_capacity_only'  => ['sometimes', 'boolean'],
            $prefix.'capacity_threshold'  => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function queryParameters(string $prefix = ''): array
    {
        return [
            $prefix.'category_slugs' => [
                'description' => 'Filter by category slugs',
                'example'     => ['programming', 'design'],
            ],
            $prefix.'fulfillment_types' => [
                'description' => 'Filter by fulfillment types (e.g., online, offline)',
                'example'     => [
                    FulfillmentTypeEnum::ONLINE_SERVICE->value, FulfillmentTypeEnum::OFFLINE_SERVICE->value,
                ],
            ],
            $prefix.'fulfillment_types.*' => [
                'description' => 'Filter by fulfillment types (e.g., online, offline)',
                'example'     => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ],

            $prefix.'difficulty_level' => [
                'description' => 'Filter by course difficulty level (e.g., beginner, intermediate, advanced)',
                'example'     => CourseDifficultyLevelEnum::BEGINNER->value,

            ],
            $prefix.'min_price' => [
                'description' => 'Only include products with a minimum price greater than or equal to this amount.',
                'example'     => 100_000,
            ],
            $prefix.'max_price' => [
                'description' => 'Only include products with a maximum price less than or equal to this amount.',
                'example'     => 500_000,
            ],
            $prefix.'with_discounts' => [
                'description' => 'When true, only include products that currently have an active discount.',
                'example'     => true,
            ],
            $prefix.'near_capacity_only' => [
                'description' => 'When true, only include products with at least one delivery option at or above the capacity threshold.',
                'example'     => true,
            ],
            $prefix.'capacity_threshold' => [
                'description' => 'Capacity ratio threshold from 0 to 1. Example: 0.8 means 80% filled.',
                'example'     => 0.8,
            ],
        ];
    }
}
