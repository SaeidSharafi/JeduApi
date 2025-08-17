<?php

declare(strict_types=1);

use App\Services\Discounts\DiscountMetadataService;
use App\Services\Discounts\OrderCalculationService;

describe('DiscountMetadataService', function (): void {
    beforeEach(function (): void {
        $this->service = app(DiscountMetadataService::class);
    });

    test('getConditions returns available discount conditions with metadata', function (): void {
        // Act
        $result = $this->service->getConditions();

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toHaveCount(2);

        $conditionKeys = collect($result)->pluck('key')->toArray();
        expect($conditionKeys)->toContain('cart_value_over')
            ->and($conditionKeys)->toContain('product_in_category');

        // Check structure of each condition
        foreach ($result as $condition) {
            expect($condition)->toHaveKeys(['key', 'name', 'description', 'handler_class', 'configuration_schema']);
        }
    });

    test('getActions returns available discount actions with metadata', function (): void {
        // Act
        $result = $this->service->getActions();

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1);

        $actionKeys = collect($result)->pluck('key')->toArray();
        expect($actionKeys)->toContain('apply_percentage_off');

        // Check structure of each action
        foreach ($result as $action) {
            expect($action)->toHaveKeys(['key', 'name', 'description', 'handler_class', 'configuration_schema']);
        }
    });

    test('getOperators returns predefined operators list', function (): void {
        // Act
        $result = $this->service->getOperators();

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toHaveCount(5);

        $operatorValues = collect($result)->pluck('value')->toArray();
        expect($operatorValues)->toContain('greater_than_or_equal')
            ->and($operatorValues)->toContain('equal');

        // Check structure of each operator
        foreach ($result as $operator) {
            expect($operator)->toHaveKeys(['value', 'label', 'symbol']);
        }
    });

    test('getTypes returns promotion types', function (): void {
        // Act
        $result = $this->service->getTypes();

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toHaveCount(2);

        $typeValues = collect($result)->pluck('value')->toArray();
        expect($typeValues)->toContain('product_specific')
            ->and($typeValues)->toContain('cart_checkout');

        // Check structure of each type
        foreach ($result as $type) {
            expect($type)->toHaveKeys(['value', 'label', 'description']);
        }
    });

    test('getValidationRules returns form validation rules', function (): void {
        // Act
        $result = $this->service->getValidationRules();

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['name', 'description', 'type', 'rules', 'coupons']);

        expect($result['name']['required'])->toBeTrue()
            ->and($result['name']['type'])->toBe('string')
            ->and($result['rules']['required'])->toBeTrue()
            ->and($result['coupons']['required'])->toBeFalse();
    });

    test('extractConfigSchema handles non-existent class', function (): void {
        // Act
        $result = $this->service->extractConfigSchema('NonExistentClass');

        // Assert
        expect($result)->toBe([]);
    });

    test('extractConfigSchema handles class without constructor', function (): void {
        // Arrange - Create a test class without constructor
        eval('class TestServiceClassWithoutConstructor {}');

        // Act
        $result = $this->service->extractConfigSchema('TestServiceClassWithoutConstructor');

        // Assert
        expect($result)->toBe([]);
    });

    test('extractConfigSchema with parameters having default values', function (): void {
        // Arrange - Create a test class with default values
        eval('
        class TestServiceClassWithDefaults {
            public function __construct(
                string $required,
                bool $optionalBool = true,
                int $optionalInt = 42,
                ?string $nullable = null
            ) {}
        }
        ');

        // Act
        $result = $this->service->extractConfigSchema('TestServiceClassWithDefaults');

        // Assert
        expect($result)->toBeArray()
            ->and($result)->toHaveKey('required')
            ->and($result['required']['required'])->toBeTrue()
            ->and($result)->toHaveKey('optionalBool')
            ->and($result['optionalBool']['required'])->toBeFalse()
            ->and($result['optionalBool']['default'])->toBeTrue()
            ->and($result)->toHaveKey('optionalInt')
            ->and($result['optionalInt']['default'])->toBe(42)
            ->and($result)->toHaveKey('nullable')
            ->and($result['nullable']['default'])->toBeNull();
    });

    test('getParameterType handles null type', function (): void {
        // Act
        $result = $this->service->getParameterType(null);

        // Assert
        expect($result)->toBe('mixed');
    });

    test('getParameterType handles float type', function (): void {
        // Arrange - Create a test class with float parameter
        eval('
        class TestServiceClassWithFloatParam {
            public function __construct(float $floatParam) {}
        }
        ');

        $reflectionClass = new ReflectionClass('TestServiceClassWithFloatParam');
        $constructor = $reflectionClass->getConstructor();
        $parameter = $constructor->getParameters()[0];
        $type = $parameter->getType();

        // Act
        $result = $this->service->getParameterType($type);

        // Assert
        expect($result)->toBe('number');
    });

    test('getParameterType handles custom class type (default case)', function (): void {
        // Arrange - Create a test class with custom object parameter
        eval('
        class CustomServiceTypeClass {}
        class TestServiceClassWithCustomParam {
            public function __construct(CustomServiceTypeClass $customParam) {}
        }
        ');

        $reflectionClass = new ReflectionClass('TestServiceClassWithCustomParam');
        $constructor = $reflectionClass->getConstructor();
        $parameter = $constructor->getParameters()[0];
        $type = $parameter->getType();

        // Act
        $result = $this->service->getParameterType($type);

        // Assert
        expect($result)->toBe('CustomServiceTypeClass');
    });

    test('generateParameterDescription creates proper descriptions', function (): void {
        // Create a mock type
        $mockType = new class {
            public function getName() { return 'string'; }
        };

        // Act
        $result = $this->service->generateParameterDescription('test_parameter_name', $mockType);

        // Assert
        expect($result)->toBe('Test Parameter Name (string)');
    });

    test('generateNameFromKey converts underscores to spaces and capitalizes', function (): void {
        // Act & Assert
        expect($this->service->generateNameFromKey('cart_value_over'))->toBe('Cart Value Over')
            ->and($this->service->generateNameFromKey('product_in_category'))->toBe('Product In Category')
            ->and($this->service->generateNameFromKey('simple_key'))->toBe('Simple Key');
    });

    test('generateDescriptionFromClass handles class names correctly', function (): void {
        // Act & Assert for different class names
        $result1 = $this->service->generateDescriptionFromClass('App\\Services\\SomeCondition');
        expect($result1)->toBe('Some');

        $result2 = $this->service->generateDescriptionFromClass('App\\Services\\SomeAction');
        expect($result2)->toBe('Some');

        $result3 = $this->service->generateDescriptionFromClass('App\\Services\\MultiWordClassAction');
        expect($result3)->toBe('Multi Word Class');

        $result4 = $this->service->generateDescriptionFromClass('SimpleClass');
        expect($result4)->toBe('Simple Class');
    });

    test('service adapts to new handlers dynamically', function (): void {
        // This test demonstrates that the service works with the actual registry
        // and will automatically adapt when new handlers are added

        // Get current conditions and actions
        $conditions = $this->service->getConditions();
        $actions = $this->service->getActions();

        // Verify we get results based on actual registry
        expect($conditions)->toBeArray()
            ->and(count($conditions))->toBeGreaterThan(0)
            ->and($actions)->toBeArray()
            ->and(count($actions))->toBeGreaterThan(0);

        // Each should have the required structure
        foreach ($conditions as $condition) {
            expect($condition)->toHaveKeys(['key', 'name', 'description', 'handler_class', 'configuration_schema']);
        }

        foreach ($actions as $action) {
            expect($action)->toHaveKeys(['key', 'name', 'description', 'handler_class', 'configuration_schema']);
        }
    });

})->group('unit', 'services', 'discounts');
