<?php

declare(strict_types=1);

use App\Rules\CheckDiscountConfigurationRule;
use App\Services\Discounts\DiscountMetadataService;
use App\Enums\Order\DiscountTypeEnum;

beforeEach(function () {
    // Create mock config classes that will be used in the tests
    if (!class_exists('MockConditionConfig')) {
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

    if (!class_exists('MockActionConfig')) {
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
    $this->rule = new CheckDiscountConfigurationRule($this->mockService);
});

describe('CheckDiscountConfigurationRule', function () {

    it('passes validation when rules array is empty', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $fail = function ($message) {
            throw new Exception($message);
        };

        // Should not throw any exception
        expect(fn() => $this->rule->validate('rules', [], $fail))->not->toThrow(Exception::class);
    });

    it('passes validation when type is not set', function () {
        $this->rule->setData([]);

        $fail = function ($message) {
            throw new Exception($message);
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition',
                'configuration' => ['value' => 100]
            ]
        ];

        // Should not throw any exception when type is not set
        expect(fn() => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('fails validation when rule structure is invalid', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toBe(__('discount.validation.missing_required_keys', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition'
                // Missing 'configuration' key
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails validation when handler is not recognized', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('unknown_handler', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn(null);

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toBe(__('discount.validation.handler_not_recognized', ['handler' => 'unknown_handler']));
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'unknown_handler',
                'configuration' => ['value' => 100]
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails validation when configuration is invalid', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toContain(__('discount.validation.configuration_invalid', [
                'handler' => 'mock_condition',
                'errors' => ''
            ]));
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition',
                'configuration' => ['value' => 'invalid_integer'] // Should be integer
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('fails validation when no condition is present and no coupons for cart_checkout', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toBe(__('discount.validation.condition_required', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type' => 'action',
                'handler' => 'mock_action',
                'configuration' => ['percentage' => 10.0]
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('passes validation when no condition but has coupons for cart_checkout', function () {
        $this->rule->setData([
            'type' => 'cart_checkout',
            'coupons' => ['COUPON1', 'COUPON2']
        ]);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $fail = function ($message) {
            throw new Exception($message);
        };

        $rules = [
            [
                'type' => 'action',
                'handler' => 'mock_action',
                'configuration' => ['percentage' => 10.0]
            ]
        ];

        // Should not throw any exception
        expect(fn() => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('fails validation when no action is present', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toBe(__('discount.validation.action_required', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition',
                'configuration' => ['value' => 100, 'operator' => 'greater_than'] // Valid config
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('passes validation with valid conditions and actions', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $fail = function ($message) {
            throw new Exception($message);
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition',
                'configuration' => ['value' => 100, 'operator' => 'greater_than']
            ],
            [
                'type' => 'action',
                'handler' => 'mock_action',
                'configuration' => ['percentage' => 15.5, 'apply_to_all' => true]
            ]
        ];

        // Should not throw any exception
        expect(fn() => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('works with product_specific discount type', function () {
        $this->rule->setData(['type' => 'product_specific']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::PRODUCT_SPECIFIC)
            ->andReturn('MockConditionConfig');

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::PRODUCT_SPECIFIC)
            ->andReturn('MockActionConfig');

        $fail = function ($message) {
            throw new Exception($message);
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition',
                'configuration' => ['value' => 50, 'operator' => 'greater_than'] // Valid config
            ],
            [
                'type' => 'action',
                'handler' => 'mock_action',
                'configuration' => ['percentage' => 20.0, 'apply_to_all' => false] // Valid config
            ]
        ];

        // Should not throw any exception
        expect(fn() => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('handles multiple validation errors in configuration', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_condition', 'condition', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockConditionConfig');

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toContain(__('discount.validation.configuration_invalid', [
                'handler' => 'mock_condition',
                'errors' => ''
            ]));
            // Should contain multiple validation errors
            expect($message)->toContain('value');
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition',
                'configuration' => [
                    'value' => -10, // Invalid: should be min:1
                    'operator' => 'invalid_operator' // Invalid: not in allowed list
                ]
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('handles invalid discount type gracefully', function () {
        $this->rule->setData(['type' => 'invalid_type']);

        $fail = function ($message) {
            throw new Exception($message);
        };

        $rules = [
            [
                'type' => 'condition',
                'handler' => 'mock_condition',
                'configuration' => ['value' => 100]
            ]
        ];

        // Should not throw any exception when type is invalid
        expect(fn() => $this->rule->validate('rules', $rules, $fail))->not->toThrow(Exception::class);
    });

    it('handles non-array rules input', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $fail = function ($message) {
            throw new Exception($message);
        };

        // Should not throw any exception when rules is not an array
        expect(fn() => $this->rule->validate('rules', 'not_an_array', $fail))->not->toThrow(Exception::class);
        expect(fn() => $this->rule->validate('rules', null, $fail))->not->toThrow(Exception::class);
    });

    it('handles empty coupons array for cart_checkout', function () {
        $this->rule->setData([
            'type' => 'cart_checkout',
            'coupons' => [] // Empty array
        ]);

        $this->mockService->shouldReceive('getConfigurationClass')
            ->with('mock_action', 'action', DiscountTypeEnum::CART_CHECKOUT)
            ->andReturn('MockActionConfig');

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toBe(__('discount.validation.condition_required', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'type' => 'action',
                'handler' => 'mock_action',
                'configuration' => ['percentage' => 10.0, 'apply_to_all' => true]
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });

    it('handles missing array keys gracefully', function () {
        $this->rule->setData(['type' => 'cart_checkout']);

        $failed = false;
        $fail = function ($message) use (&$failed) {
            $failed = true;
            expect($message)->toBe(__('discount.validation.missing_required_keys', ['attribute' => 'rules']));
        };

        $rules = [
            [
                'handler' => 'mock_condition',
                'configuration' => ['value' => 100]
                // Missing 'type' key
            ]
        ];

        $this->rule->validate('rules', $rules, $fail);
        expect($failed)->toBeTrue();
    });
});
