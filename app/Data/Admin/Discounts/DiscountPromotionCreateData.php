<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use App\Data\Transformer\CarbonFromJalaliString;
use App\Enums\Order\DiscountTypeEnum;
use App\Rules\CheckDiscountConfigurationRule;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DiscountPromotionCreateData extends Data
{
    public function __construct(
        public string $name,
        public DiscountTypeEnum $type,
        #[DataCollectionOf(DiscountPromotionRuleCreateData::class)]
        public array $rules,
        public bool $is_active,
        public ?string $description = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $starts_at = null,
        #[WithCast(CarbonFromJalaliString::class, 'Y-m-d')]
        public ?Carbon $ends_at = null,
        public int $priority = 0,
        public bool $stop_processing_subsequent_rules = false,
        public ?int $usage_limit_total = null,
        public ?int $usage_limit_per_customer = null,
        #[DataCollectionOf(DiscountCouponCreateData::class)]
        public array $coupons = [],
    ) {}

    public static function rules(?ValidationContext $context = null): array
    {
        $now = verta()->format('Y-m-d');

        return [
            'name'                             => ['required', 'string', 'max:255'],
            'type'                             => ['required', 'string', Rule::enum(DiscountTypeEnum::class)],
            'description'                      => ['nullable', 'string', 'max:1000'],
            'is_active'                        => ['required', 'boolean'],
            'starts_at'                        => ['nullable', 'jdate:Y-m-d', 'jdate_after_equal:'.$now.',Y-m-d'],
            'ends_at'                          => ['nullable', 'jdate:Y-m-d', 'jdate_after:'.request('starts_at').',Y-m-d'],
            'priority'                         => ['integer', 'min:0', 'max:1000'],
            'stop_processing_subsequent_rules' => ['boolean'],
            'usage_limit_total'                => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer'         => ['nullable', 'integer', 'min:1'],
            'rules'                            => ['required', 'array', 'min:1', app(CheckDiscountConfigurationRule::class)],
            'rules.*.type'                     => ['required', 'string', 'in:condition,action'],
            'rules.*.handler'                  => ['required', 'string'],
            'rules.*.configuration'            => ['required', 'array'],
            'coupons'                          => ['array', 'prohibited_if:type,'.DiscountTypeEnum::PRODUCT_SPECIFIC->value],
            'coupons.*.code'                   => ['required_with:coupons', 'string', 'max:50', 'alpha_num'],
            'coupons.*.usage_limit'            => ['nullable', 'integer', 'min:1'],
            'coupons.*.is_active'              => ['boolean'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'rules.*.type.required'          => __('validation.custom.discount.rules.type.required'),
            'rules.*.type.in'                => __('validation.custom.discount.rules.type.in'),
            'rules.*.handler.required'       => __('validation.custom.discount.rules.handler.required'),
            'rules.*.configuration.required' => __('validation.custom.discount.rules.configuration.required'),
            'rules.*.configuration.array'    => __('validation.custom.discount.rules.configuration.array'),
            'coupons.*.code.required_with'   => __('validation.custom.discount.coupons.code.required'),
            'coupons.*.usage_limit.integer'  => __('validation.custom.discount.coupons.usage_limit.integer'),
            'coupons.*.usage_limit.min'      => __('validation.custom.discount.coupons.usage_limit.min'),
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'The name of the discount promotion.',
                'example'     => 'Back to School Sale',
            ],
            'type' => [
                'description' => 'The type of discount.',
                'example'     => 'order',
            ],
            'description' => [
                'description' => 'Description of the promotion.',
                'example'     => 'Special discount for school supplies.',
            ],
            'is_active' => [
                'description' => 'Whether the promotion is active.',
                'example'     => true,
            ],
            'starts_at' => [
                'description' => 'Start date in Jalali format.',
                'example'     => '1402-01-01',
            ],
            'ends_at' => [
                'description' => 'End date in Jalali format.',
                'example'     => '1402-01-10',
            ],
            'priority' => [
                'description' => 'Priority of the promotion.',
                'example'     => 10,
            ],
            'stop_processing_subsequent_rules' => [
                'description' => 'Whether to stop processing subsequent rules.',
                'example'     => false,
            ],
            'usage_limit_total' => [
                'description' => 'Total usage limit.',
                'example'     => 100,
            ],
            'usage_limit_per_customer' => [
                'description' => 'Usage limit per customer.',
                'example'     => 1,
            ],
            'rules' => [
                'description' => 'Array of discount rules.',
                'example'     => [
                    ['type' => 'condition', 'handler' => 'min_order_amount', 'configuration' => ['amount' => 10000]],
                ],
            ],
            'rules.*.type' => [
                'description' => 'Type of rule (condition/action).',
                'example'     => 'condition',
            ],
            'rules.*.handler' => [
                'description' => 'Handler for the rule.',
                'example'     => 'min_order_amount',
            ],
            'rules.*.configuration' => [
                'description' => 'Configuration for the rule.',
                'example'     => json_encode(['amount' => 10000]),
            ],
            'coupons' => [
                'description' => 'Array of discount coupons.',
                'example'     => [
                    ['code' => 'BACK2SCHOOL', 'usage_limit' => 10, 'is_active' => true],
                ],
            ],
            'coupons.*.code' => [
                'description' => 'Coupon code.',
                'example'     => 'BACK2SCHOOL',
            ],
            'coupons.*.usage_limit' => [
                'description' => 'Usage limit for the coupon.',
                'example'     => 10,
            ],
            'coupons.*.is_active' => [
                'description' => 'Whether the coupon is active.',
                'example'     => true,
            ],
        ];
    }
}
