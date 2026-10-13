<?php

declare(strict_types=1);

use App\Enums\Order\DiscountTypeEnum;
use App\Rules\CheckDiscountConfigurationRule;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffData;
use App\Services\Discounts\Configs\CartValueConditionConfigData;
use App\Services\Discounts\Configs\CartValueTierConditionConfigData;
use App\Services\Discounts\DiscountMetadataService;

beforeEach(function (): void {
    // Create mock config classes that will be used in the tests
    if (! class_exists('MockConditionConfig')) {
        eval('
            class MockConditionConfig {
                public function __construct(
                    public int $value,
                    public string $operator = "greater_than"
                ) {}

                public static function rules(): array {
                    return [
                        "value" => ["required", "integer", "min:1"],
                        "operator" => ["required", "string", "in:greater_than,less_than"]
                    ];
                }
            }
        ');
    }

    if (! class_exists('MockActionConfig')) {
        eval('
            class MockActionConfig {
                public function __construct(
                    public float $percentage,
                    public bool $apply_to_all = true
                ) {}

                public static function rules(): array {
                    return [
                        "percentage" => ["required", "numeric", "min:0", "max:100"],
                        "apply_to_all" => ["boolean"]
                    ];
                }
            }
        ');
    }

    // Mock the service to return our mock config classes
    $this->mockService = $this->mock(DiscountMetadataService::class);
    $this->rule        = new CheckDiscountConfigurationRule($this->mockService);
});

describe('CheckDiscountConfigurationRule', function (): void {

    it('passes validation when rules array is empty', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $fail = function ($message): void {
            throw new Exception($message);
        };

        // Should not throw any exception
        expect(fn () => $this->rule->validate('rules', [], $fail))->not->toThrow(Exception::class);
    });

    it('passes validation when type is not set', function (): void {
        $this->rule->setData([]);

        $fail = function ($message): void {
            throw new Exception($message);
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'mock_condition',
                'configuration' => ['value' => 100],
            ],
        ];

        // Should not throw any exception when type is not set
        expect(fn () => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('fails validation when rule structure is invalid', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.missing_required_keys', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type'    => 'condition',
                'handler' => 'mock_condition',
                // Missing 'configuration' key
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails validation when handler is not recognized', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('unknown_handler', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(null);

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.handler_not_recognized', ['handler' => 'unknown_handler']));
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'unknown_handler',
                'configuration' => ['value' => 100],
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails validation when configuration is invalid', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toContain(__('discount.validation.configuration_invalid', [
                'handler' => 'mock_condition',
                'errors'  => '',
            ]));
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'mock_condition',
                'configuration' => ['value' => 'invalid_integer'], // Should be integer
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails validation when no condition is present and no coupons for cart_checkout', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.condition_required', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type'          => 'action',
                'handler'       => 'mock_action',
                'configuration' => ['percentage' => 10.0],
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('passes validation when no condition but has coupons for cart_checkout', function (): void {
        $this->rule->setData([
            'type'    => 'cart_checkout',
            'coupons' => ['COUPON1', 'COUPON2'],
        ]);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $fail = function ($message): void {
            throw new Exception($message);
        };

        $rules = [
            [
                'type'          => 'action',
                'handler'       => 'mock_action',
                'configuration' => ['percentage' => 10.0],
            ],
        ];

        // Should not throw any exception
        expect(fn () => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('fails validation when no action is present', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.action_required', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'mock_condition',
                'configuration' => ['value' => 100, 'operator' => 'greater_than'], // Valid config
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('passes validation with valid conditions and actions', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $fail = function ($message): void {
            throw new Exception($message);
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'mock_condition',
                'configuration' => ['value' => 100, 'operator' => 'greater_than'],
            ],
            [
                'type'          => 'action',
                'handler'       => 'mock_action',
                'configuration' => ['percentage' => 15.5, 'apply_to_all' => true],
            ],
        ];

        // Should not throw any exception
        expect(fn () => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('works with product_specific discount type', function (): void {
        $this->rule->setData(['type' => 'product_specific']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::PRODUCT_SPECIFIC)
            ->andReturn('MockConditionConfig');

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::PRODUCT_SPECIFIC)
            ->andReturn('MockActionConfig');

        $fail = function ($message): void {
            throw new Exception($message);
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'mock_condition',
                'configuration' => ['value' => 50, 'operator' => 'greater_than'], // Valid config
            ],
            [
                'type'          => 'action',
                'handler'       => 'mock_action',
                'configuration' => ['percentage' => 20.0, 'apply_to_all' => false], // Valid config
            ],
        ];

        // Should not throw any exception
        expect(fn () => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('handles multiple validation errors in configuration', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toContain(__('discount.validation.configuration_invalid', [
                'handler' => 'mock_condition',
                'errors'  => '',
            ]));
            // Should contain multiple validation errors
            expect($message)->toContain('value');
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'mock_condition',
                'configuration' => [
                    'value'    => -10, // Invalid: should be min:1
                    'operator' => 'invalid_operator', // Invalid: not in allowed list
                ],
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('handles invalid discount type gracefully', function (): void {
        $this->rule->setData(['type' => 'invalid_type']);

        $fail = function ($message): void {
            throw new Exception($message);
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'mock_condition',
                'configuration' => ['value' => 100],
            ],
        ];

        // Should not throw any exception when type is invalid
        expect(fn () => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('handles non-array rules input', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $fail = function ($message): void {
            throw new Exception($message);
        };

        // Should not throw any exception when rules is not an array
        expect(fn () => $this->rule->validate('rules', 'not_an_array', $fail))->not->toThrow(Exception::class);
        expect(fn () => $this->rule->validate('rules', null, $fail))->not->toThrow(Exception::class);
    });

    it('handles empty coupons array for cart_checkout', function (): void {
        $this->rule->setData([
            'type'    => 'cart_checkout',
            'coupons' => [], // Empty array
        ]);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.condition_required', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type'          => 'action',
                'handler'       => 'mock_action',
                'configuration' => ['percentage' => 10.0, 'apply_to_all' => true],
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('handles missing array keys gracefully', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.missing_required_keys', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'handler'       => 'mock_condition',
                'configuration' => ['value' => 100],
                // Missing 'type' key
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails when the consuming tier action has no tier condition', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('cart_value_over', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(CartValueConditionConfigData::class);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('apply_tiered_percentage_off', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(ApplyTieredPercentageOffData::class);

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.tier_pair_incomplete', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'cart_value_over',
                'configuration' => ['operator' => '>=', 'value' => 1000, 'measure_whole_commitment' => false],
            ],
            [
                'type'          => 'action',
                'handler'       => 'apply_tiered_percentage_off',
                'configuration' => [],
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails when the tier condition has no consuming action', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('cart_value_tier', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(CartValueTierConditionConfigData::class);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('apply_percentage_off', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(ApplyPercentageDiscountConfigData::class);

        $failed = false;
        $fail   = function ($message) use (&$failed): void {
            $failed = true;
            expect($message)->toBe(__('discount.validation.tier_pair_incomplete', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'cart_value_tier',
                'configuration' => ['tiers' => [['min_amount' => 1000, 'percentage' => 5]]],
            ],
            [
                'type'          => 'action',
                'handler'       => 'apply_percentage_off',
                'configuration' => ['percentage' => 10],
            ],
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('accepts a tier condition paired with its consuming action', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('cart_value_tier', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(CartValueTierConditionConfigData::class);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('apply_tiered_percentage_off', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(ApplyTieredPercentageOffData::class);

        $fail = function ($message): void {
            throw new Exception($message);
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'cart_value_tier',
                'configuration' => ['tiers' => [['min_amount' => 1000, 'percentage' => 5]]],
            ],
            [
                'type'          => 'action',
                'handler'       => 'apply_tiered_percentage_off',
                'configuration' => [],
            ],
        ];

        expect(fn () => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('accepts the operator spelling the admin metadata endpoint publishes', function (): void {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('cart_value_over', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(CartValueConditionConfigData::class);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('apply_percentage_off', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(ApplyPercentageDiscountConfigData::class);

        $fail = function ($message): void {
            throw new Exception($message);
        };

        $rules = [
            [
                'type'          => 'condition',
                'handler'       => 'cart_value_over',
                'configuration' => ['operator' => 'greater_than_or_equal', 'value' => 1000, 'measure_whole_commitment' => false],
            ],
            [
                'type'          => 'action',
                'handler'       => 'apply_percentage_off',
                'configuration' => ['percentage' => 15],
            ],
        ];

        expect(fn () => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });
});
