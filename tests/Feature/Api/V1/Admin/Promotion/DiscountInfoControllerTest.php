<?php

declare(strict_types=1);

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('DiscountInfoController', function (): void {
    beforeEach(function (): void {
        $this->authorized_user([App\Enums\PermissionEnum::DISCOUNT_VIEW_ANY]);
        $this->staff = $this->user;
    });
    test('index returns discount metadata', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cart' => [
                        'conditions' => [
                            '*' => [
                                'key',
                                'name',
                                'description',
                                'handler_class',
                                'configuration_schema',
                            ],
                        ],
                        'actions' => [
                            '*' => [
                                'key',
                                'name',
                                'description',
                                'configuration_schema',
                            ],
                        ],
                    ],
                    'product' => [
                        'conditions' => [
                            '*' => [
                                'key',
                                'name',
                                'description',
                                'handler_class',
                                'configuration_schema',
                            ],
                        ],
                        'actions' => [
                            '*' => [
                                'key',
                                'name',
                                'description',
                                'configuration_schema',
                            ],
                        ],
                    ],
                ],
            ]);

    });

    test('conditions returns available discount conditions dynamically', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cart' => [
                        '*' => [
                            'key',
                            'name',
                            'description',
                            'handler_class',
                            'configuration_schema',
                        ],
                    ],
                    'product' => [
                        '*' => [
                            'key',
                            'name',
                            'description',
                            'handler_class',
                            'configuration_schema',
                        ],
                    ],
                ],
            ]);

        $conditions = $response->json('data');

        // Should have the two existing conditions
        expect($conditions)->toHaveCount(2);

        $conditionKeys = collect($conditions['cart'])->pluck('key')->toArray();
        expect($conditionKeys)->toContain('cart_value_over')
            ->and($conditionKeys)->toContain('product_in_category');
        $conditionKeys = collect($conditions['product'])->pluck('key')->toArray();
        expect($conditionKeys)->toContain('product_in_category');
    });

    test('conditions generates proper names from keys', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        // Assert
        $data               = $response->json('data');
        $cartConditions     = collect($data['cart']);
        $poroductConditions = collect($data['product']);
        $cartValueCondition = $cartConditions->firstWhere('key', 'cart_value_over');
        expect($cartValueCondition['name'])->toBe(__('discount.handlers.cart_value_over.name'));

        $categoryCondition = $cartConditions->firstWhere('key', 'product_in_category');
        expect($categoryCondition['name'])->toBe(__('discount.handlers.product_in_category.name'));

        $productCategoryCondition = $poroductConditions->firstWhere('key', 'product_in_category');
        expect($productCategoryCondition['name'])->toBe(__('discount.handlers.product_in_category.name'));
    });

    test('conditions includes configuration schema from config classes', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        $data               = $response->json('data');
        $cartConditions     = collect($data['cart']);
        $poroductConditions = collect($data['product']);

        $cartValueCondition = $cartConditions->firstWhere('key', 'cart_value_over');
        expect($cartValueCondition['configuration_schema'])->toBeArray()
            ->and($cartValueCondition['configuration_schema'])->toHaveKeys([
                'value', 'operator', 'measure_whole_commitment',
            ]);

        $productValueCondition = $poroductConditions->firstWhere('key', 'product_in_category');
        expect($productValueCondition['configuration_schema'])->toBeArray()
            ->and($productValueCondition['configuration_schema'])->toHaveKeys(['category_ids', 'match_policy']);
    });

    test('cart value condition wording states a measurement-only choice in both locales', function (): void {
        $expected = [
            'en' => [
                'label'       => 'Measure the Whole Commitment',
                'description' => "Only changes how this threshold is measured: the customer's whole commitment (deposit lines counted at their paid deposit) instead of only the lines being paid in full. It never changes what gets discounted — deposits are never discounted.",
            ],
            'fa' => [
                'label'       => 'سنجش کل تعهد مشتری',
                'description' => 'تنها نحوه سنجش این شرط را تغییر می‌دهد: کل تعهد مشتری (اقلام پیش‌پرداختی با مبلغ پیش‌پرداخت پرداخت‌شده) به‌جای فقط اقلامی که نقداً تسویه می‌شوند. این تنظیم هرگز تعیین نمی‌کند چه چیزی تخفیف می‌خورد؛ پیش‌پرداخت‌ها هیچ‌گاه تخفیف نمی‌خورند.',
            ],
        ];

        foreach ($expected as $locale => $strings) {
            app()->setLocale($locale);

            $response = $this->get('/api/v1/admin/discount-info/conditions');
            $response->assertOk();

            $schema = collect($response->json('data.cart'))
                ->firstWhere('key', 'cart_value_over')['configuration_schema'];

            // The exact key set is pinned, so no other measurement knob (the
            // old "include prepayments" flag included) can silently return.
            expect(array_keys($schema))->toEqualCanonicalizing(['operator', 'value', 'measure_whole_commitment'])
                ->and($schema['measure_whole_commitment']['label'])->toBe($strings['label'])
                ->and($schema['measure_whole_commitment']['description'])->toBe($strings['description']);
        }

        app()->setLocale('en');
    });

    test('actions returns available discount actions dynamically', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/actions');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cart' => [
                        '*' => [
                            'key',
                            'name',
                            'description',
                            'configuration_schema',
                        ],
                    ],
                    'product' => [
                        '*' => [
                            'key',
                            'name',
                            'description',
                            'configuration_schema',
                        ],
                    ],
                ],
            ]);

        $actions = $response->json('data');

        // Should have the existing action
        expect($actions)->toHaveCount(2);

        $actionKeys = collect($actions['cart'])->pluck('key')->toArray();
        expect($actionKeys)->toContain('apply_percentage_off');
        $actionKeys = collect($actions['product'])->pluck('key')->toArray();
        expect($actionKeys)->toContain('apply_percentage_off_product');
    });

    test('actions generates proper configuration schema', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/actions');

        // Assert
        $data             = $response->json('data');
        $cartActions      = collect($data['cart']);
        $productActions   = collect($data['product']);
        $percentageAction = $cartActions->firstWhere('key', 'apply_percentage_off');
        expect($percentageAction['configuration_schema'])->toBeArray()
            ->and($percentageAction['configuration_schema'])->toHaveKey('percentage');

        $percentageConfig = $percentageAction['configuration_schema']['percentage'];
        expect($percentageConfig['type'])->toBe('integer')
            ->and($percentageConfig['required'])->toBeTrue();

        $productPercentageAction = $productActions->firstWhere('key', 'apply_percentage_off_product');
        expect($productPercentageAction['configuration_schema'])->toBeArray()
            ->and($productPercentageAction['configuration_schema'])->toHaveKey('percentage');
        $productPercentageConfig = $productPercentageAction['configuration_schema']['percentage'];
        expect($productPercentageConfig['type'])->toBe('integer')
            ->and($productPercentageConfig['required'])->toBeTrue();
    });

    test('operators returns predefined operators list', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/operators');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'value',
                        'label',
                        'symbol',
                    ],
                ],
            ]);

        $operators = $response->json('data');
        expect($operators)->toHaveCount(5);

        $operatorValues = collect($operators)->pluck('value')->toArray();
        expect($operatorValues)->toContain('greater_than_or_equal')
            ->and($operatorValues)->toContain('equal');
    });

    test('types returns promotion types', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/types');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'value',
                        'label',
                        'description',
                    ],
                ],
            ]);

        $types = $response->json('data');
        expect($types)->toHaveCount(2);

        $typeValues = collect($types)->pluck('value')->toArray();
        expect($typeValues)->toContain('product_specific')
            ->and($typeValues)->toContain('cart_checkout');
    });

    test('dynamic generation adapts to new handlers', function (): void {
        // This test demonstrates that when new handlers are added to OrderCalculationService,
        // they automatically appear in the API without manual updates

        // Use the real controller with real reflection
        // This verifies the actual dynamic behavior works
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        // Assert that it works with the current handlers
        $response->assertOk();
        $conditions = $response->json('data')['cart'];

        // Verify we get results based on actual registry
        expect($conditions)->toBeArray()
            ->and(count($conditions))->toBeGreaterThan(0);

        // Each condition should have the required structure
        foreach ($conditions as $condition) {
            expect($condition)->toHaveKeys(['key', 'name', 'description', 'handler_class', 'configuration_schema']);
        }
    });

    test('lists the cart value tier condition and the config-free consuming action', function (): void {
        $conditions = $this->get('/api/v1/admin/discount-info/conditions')->assertOk()->json('data.cart');
        $tier       = collect($conditions)->firstWhere('key', 'cart_value_tier');

        expect($tier)->not->toBeNull()
            ->and($tier['name'])->toBe(__('discount.handlers.cart_value_tier.name'))
            ->and($tier['handler_class'])->toBe(App\Services\Discounts\Cart\Conditions\CartValueTierCondition::class)
            ->and(array_keys($tier['configuration_schema']))->toBe(['tiers'])
            ->and($tier['configuration_schema']['tiers']['item_class'])->toBe(App\Services\Discounts\Configs\TierData::class)
            ->and($tier['configuration_schema']['tiers']['item_schema'])->toHaveKeys(['min_amount', 'percentage']);

        $actions = $this->get('/api/v1/admin/discount-info/actions')->assertOk()->json('data.cart');
        $action  = collect($actions)->firstWhere('key', 'apply_tiered_percentage_off');

        expect($action)->not->toBeNull()
            ->and($action['name'])->toBe(__('discount.handlers.apply_tiered_percentage_off.name'))
            ->and($action['configuration_schema'])->toBe([]);
    });

})->group('unit', 'controllers', 'discounts');

describe('DiscountInfoController with unauthorized user', function (): void {
    test('all endpoints require authentication', function (): void {
        $endpoints = [
            '/api/v1/admin/discount-info/conditions',
            '/api/v1/admin/discount-info/actions',
            '/api/v1/admin/discount-info/operators',
            '/api/v1/admin/discount-info/types',
        ];

        foreach ($endpoints as $endpoint) {
            // Act
            $response = $this->get($endpoint);

            // Assert
            $response->assertUnauthorized();
        }
    });
});
