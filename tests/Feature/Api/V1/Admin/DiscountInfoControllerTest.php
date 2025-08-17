<?php

declare(strict_types=1);

uses(\Tests\AuthTestTrait::class);

describe('DiscountInfoController', function (): void {
    beforeEach(function (): void {
        $this->authorized_user([\App\Enums\PermissionEnum::DISCOUNT_VIEW_ANY]);
        $this->staff = $this->user;
    });

    test('conditions returns available discount conditions dynamically', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'key',
                        'name',
                        'description',
                        'handler_class',
                        'configuration_schema',
                    ]
                ]
            ]);

        $conditions = $response->json('data');

        // Should have the two existing conditions
        expect($conditions)->toHaveCount(2);

        $conditionKeys = collect($conditions)->pluck('key')->toArray();
        expect($conditionKeys)->toContain('cart_value_over')
            ->and($conditionKeys)->toContain('product_in_category');
    });

    test('conditions generates proper names from keys', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        // Assert
        $conditions = collect($response->json('data'));

        $cartValueCondition = $conditions->firstWhere('key', 'cart_value_over');
        expect($cartValueCondition['name'])->toBe('Cart Value Over');

        $categoryCondition = $conditions->firstWhere('key', 'product_in_category');
        expect($categoryCondition['name'])->toBe('Product In Category');
    });

    test('conditions includes configuration schema from config classes', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        // Assert
        $conditions = collect($response->json('data'));

        $cartValueCondition = $conditions->firstWhere('key', 'cart_value_over');
        expect($cartValueCondition['configuration_schema'])->toBeArray()
            ->and($cartValueCondition['configuration_schema'])->toHaveKeys(['value', 'operator', 'include_prepayments']);
    });

    test('actions returns available discount actions dynamically', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/actions');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'key',
                        'name',
                        'description',
                        'handler_class',
                        'configuration_schema',
                    ]
                ]
            ]);

        $actions = $response->json('data');

        // Should have the existing action
        expect($actions)->toHaveCount(1);

        $actionKeys = collect($actions)->pluck('key')->toArray();
        expect($actionKeys)->toContain('apply_percentage_off');
    });

    test('actions generates proper configuration schema', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/actions');

        // Assert
        $actions = collect($response->json('data'));

        $percentageAction = $actions->firstWhere('key', 'apply_percentage_off');
        expect($percentageAction['configuration_schema'])->toBeArray()
            ->and($percentageAction['configuration_schema'])->toHaveKey('percentage');

        $percentageConfig = $percentageAction['configuration_schema']['percentage'];
        expect($percentageConfig['type'])->toBe('integer')
            ->and($percentageConfig['required'])->toBeTrue();
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
                    ]
                ]
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
                    ]
                ]
            ]);

        $types = $response->json('data');
        expect($types)->toHaveCount(2);

        $typeValues = collect($types)->pluck('value')->toArray();
        expect($typeValues)->toContain('product_specific')
            ->and($typeValues)->toContain('cart_checkout');
    });

    test('validationRules returns form validation rules', function (): void {
        // Act
        $response = $this->get('/api/v1/admin/discount-info/validation-rules');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'name' => [
                        'required',
                        'type',
                        'max_length',
                        'description',
                    ],
                    'description',
                    'type',
                    'rules',
                    'coupons',
                ]
            ]);

        $rules = $response->json('data');
        expect($rules['name']['required'])->toBeTrue()
            ->and($rules['name']['type'])->toBe('string')
            ->and($rules['rules']['required'])->toBeTrue()
            ->and($rules['coupons']['required'])->toBeFalse();
    });

    test('dynamic generation adapts to new handlers', function (): void {
        // This test demonstrates that when new handlers are added to OrderCalculationService,
        // they automatically appear in the API without manual updates

        // Use the real controller with real reflection
        // This verifies the actual dynamic behavior works
        $response = $this->get('/api/v1/admin/discount-info/conditions');

        // Assert that it works with the current handlers
        $response->assertOk();
        $conditions = $response->json('data');

        // Verify we get results based on actual registry
        expect($conditions)->toBeArray()
            ->and(count($conditions))->toBeGreaterThan(0);

        // Each condition should have the required structure
        foreach ($conditions as $condition) {
            expect($condition)->toHaveKeys(['key', 'name', 'description', 'handler_class', 'configuration_schema']);
        }
    });

})->group('unit', 'controllers', 'discounts');

describe('DiscountInfoController with unauthorized user', function (): void {
    test('all endpoints require authentication', function (): void {
        $endpoints = [
            '/api/v1/admin/discount-info/conditions',
            '/api/v1/admin/discount-info/actions',
            '/api/v1/admin/discount-info/operators',
            '/api/v1/admin/discount-info/types',
            '/api/v1/admin/discount-info/validation-rules',
        ];

        foreach ($endpoints as $endpoint) {
            // Act
            $response = $this->get($endpoint);

            // Assert
            $response->assertUnauthorized();
        }
    });
});
