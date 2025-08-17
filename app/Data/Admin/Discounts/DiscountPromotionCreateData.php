<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use App\Enums\Order\DiscountTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DiscountPromotionCreateData extends Data
{
    public function __construct(
        public string $name,
        public string $type,
        #[DataCollectionOf(DiscountPromotionRuleCreateData::class)]
        public array $rules,
        public ?string $description = null,
        public bool $is_active = false,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public int $priority = 0,
        public bool $stop_processing_subsequent_rules = false,
        public ?int $usage_limit_total = null,
        public ?int $usage_limit_per_customer = null,
        #[DataCollectionOf(DiscountCouponCreateData::class)]
        public array $coupons = [],
    ) {
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'name'                             => ['required', 'string', 'max:255'],
            'type'                             => ['required', 'string', Rule::enum(DiscountTypeEnum::class)],
            'description'                      => ['nullable', 'string', 'max:1000'],
            'is_active'                        => ['boolean'],
            'starts_at'                        => ['nullable', 'date', 'after_or_equal:today'],
            'ends_at'                          => ['nullable', 'date', 'after:starts_at'],
            'priority'                         => ['integer', 'min:0', 'max:1000'],
            'stop_processing_subsequent_rules' => ['boolean'],
            'usage_limit_total'                => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer'         => ['nullable', 'integer', 'min:1'],
            'rules'                            => ['required', 'array', 'min:1'],
            'rules.*.type'                     => ['required', 'string', 'in:condition,action'],
            'rules.*.handler'                  => ['required', 'string'],
            'rules.*.configuration'            => ['required', 'array'],
            'coupons'                          => ['array'],
            'coupons.*.code'                   => ['required_with:coupons', 'string', 'max:50', 'alpha_num'],
            'coupons.*.usage_limit'            => ['nullable', 'integer', 'min:1'],
            'coupons.*.is_active'              => ['boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'name'        => [
                'description' => 'Name of the promotion',
                'example'     => 'Summer Sale 2025',
            ],
            'type'        => [
                'description' => 'Type of promotion',
                'example'     => DiscountTypeEnum::CART_CHECKOUT->value,
            ],
            'description' => [
                'description' => 'Internal description for admins',
                'example'     => '10% off all courses during summer',
            ],
            'is_active'   => [
                'description' => 'Whether the promotion is active',
                'example'     => true,
            ],
            'starts_at'   => [
                'description' => 'When the promotion starts (ISO 8601 format)',
                'example'     => '2025-06-01T00:00:00Z',
            ],
            'ends_at'     => [
                'description' => 'When the promotion ends (ISO 8601 format)',
                'example'     => '2025-08-31T23:59:59Z',
            ],
            'priority'    => [
                'description' => 'Priority for conflict resolution (higher runs first)',
                'example'     => 10,
            ],
            'rules'       => [
                'description' => 'Array of promotion rules (conditions and actions)',
                'example'     => [
                    [
                        'type'          => 'condition',
                        'handler'       => 'cart_value_over',
                        'configuration' => [
                            'operator'            => '>=',
                            'value'               => 50000,
                            'include_prepayments' => false
                        ]
                    ],
                    [
                        'type'          => 'action',
                        'handler'       => 'apply_percentage_off',
                        'configuration' => [
                            'percentage' => 10
                        ]
                    ]
                ]
            ],
            'coupons'     => [
                'description' => 'Array of coupon codes for this promotion',
                'example'     => [
                    [
                        'code'        => 'SUMMER10',
                        'usage_limit' => 100,
                        'is_active'   => true
                    ]
                ]
            ],
        ];
    }
}
