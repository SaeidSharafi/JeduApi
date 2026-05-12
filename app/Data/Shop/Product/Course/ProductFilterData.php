<?php

declare(strict_types=1);

namespace App\Data\Shop\Product\Course;

use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class ProductFilterData extends Data
{
    public function __construct(
        public ?array $category_slugs = null, // Use IDs for global search
        public ?array $fulfillment_types = null,
        public ?string $difficulty_level = null,
        public ?int $min_price = null,
        public ?int $max_price = null,
        public ?bool $with_discounts = null,
        public ?bool $is_available_now = null,
        public ?bool $near_capacity_only = null,
        public ?float $capacity_threshold = null,
        public ?string $availability_status = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $registration_starts_after = null,
        #[WithCast(CarbonFromJalaliString::class, format: 'Y-m-d')]
        public ?Carbon $registration_ends_before = null,
        #[WithCast(CarbonFromJalaliString::class, format: 'Y-m-d')]
        public ?Carbon $available_from = null,
        #[WithCast(CarbonFromJalaliString::class, format: 'Y-m-d')]
        public ?Carbon $available_to = null,

    ) {}

    public static function rules(?ValidationContext $context = null, string $prefix = ''): array
    {
        return [
            $prefix.'category_slugs'            => ['sometimes', 'array'],
            $prefix.'category_slugs.*'          => ['string'],
            $prefix.'fulfillment_types'         => ['sometimes', 'array'],
            $prefix.'fulfillment_types.*'       => ['string', Rule::enum(FulfillmentTypeEnum::class)],
            $prefix.'difficulty_level'          => [
                'sometimes', 'string', Rule::enum(CourseDifficultyLevelEnum::class)
            ],
            $prefix.'availability_status'       => ['sometimes', 'string', Rule::enum(AvailabilityStatusEnum::class)],
            $prefix.'min_price'                 => ['sometimes', 'integer', 'min:0'],
            $prefix.'max_price'                 => ['sometimes', 'integer', "gt:{$prefix}min_price"],
            $prefix.'with_discounts'            => ['sometimes', 'boolean'],
            $prefix.'near_capacity_only'        => ['sometimes', 'boolean'],
            $prefix.'capacity_threshold'        => ['sometimes', 'numeric', 'min:0', 'max:1'],
            $prefix.'registration_starts_after' => ['sometimes', 'date'],
            $prefix.'registration_ends_before'  => ['sometimes', 'date', 'after:registration_starts_after'],
            $prefix.'available_from'            => ['sometimes', 'date'],
            $prefix.'available_to'              => ['sometimes', 'date', 'after:available_from'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function queryParameters(string $prefix = ''): array
    {
        return [
            $prefix.'category_slugs'      => [
                'description' => 'Filter by category slugs',
                'example'     => ['programming', 'design'],
            ],
            $prefix.'fulfillment_types'   => [
                'description' => 'Filter by fulfillment types (e.g., online, offline)',
                'example'     => [
                    FulfillmentTypeEnum::ONLINE_SERVICE->value, FulfillmentTypeEnum::OFFLINE_SERVICE->value,
                ],
            ],
            $prefix.'fulfillment_types.*' => [
                'description' => 'Filter by fulfillment types (e.g., online, offline)',
                'example'     => FulfillmentTypeEnum::ONLINE_SERVICE->value,
            ],

            $prefix.'difficulty_level'    => [
                'description' => 'Filter by course difficulty level (e.g., beginner, intermediate, advanced)',
                'example'     => CourseDifficultyLevelEnum::BEGINNER->value,

            ],
            $prefix . 'availability_status' => [
                'description' => 'Filter by the temporal state of the product (e.g., past, upcoming, ongoing). Note: This parameter overrides `available_from` and `available_to` if provided.',
                'example'     => AvailabilityStatusEnum::PAST->value,
            ],
            $prefix.'min_price'           => [
                'description' => 'Only include products with a minimum price greater than or equal to this amount.',
                'example'     => 100_000,
            ],
            $prefix.'max_price'           => [
                'description' => 'Only include products with a maximum price less than or equal to this amount.',
                'example'     => 500_000,
            ],
            $prefix.'with_discounts'      => [
                'description' => 'When true, only include products that currently have an active discount.',
                'example'     => true,
            ],
            $prefix.'near_capacity_only'  => [
                'description' => 'When true, only include products with at least one delivery option at or above the capacity threshold.',
                'example'     => true,
            ],
            $prefix.'capacity_threshold'  => [
                'description' => 'Capacity ratio threshold from 0 to 1. Example: 0.8 means 80% filled.',
                'example'     => 0.8,
            ],
            $prefix . 'registration_starts_after' => [
                'description' => 'Filter products where registration opens on or after this date.',
                'example'     => '1404-01-01',
            ],
            $prefix . 'registration_ends_before' => [
                'description' => 'Filter products where registration closes on or before this date.',
                'example'     => '1404-02-01',
            ],
            $prefix . 'available_from' => [
                'description' => 'Filter products that become accessible to users starting from this date.',
                'example'     => '1404-01-01',
            ],
            $prefix . 'available_to' => [
                'description' => 'Filter products that stop being accessible to users after this date.',
                'example'     => '1404-02-01',
            ],
        ];
    }
}
