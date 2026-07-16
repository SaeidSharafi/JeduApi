<?php

declare(strict_types=1);

use App\Enums\Order\DiscountTypeEnum;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\DiscountMetadataService;

describe('DiscountMetadataService', function (): void {
    beforeEach(function (): void {
        $this->mockRegistry = $this->mock(DiscountHandlerRegistry::class);
        $this->service      = new DiscountMetadataService($this->mockRegistry);
    });

    it('returns correct metadata structure for empty handlers', function (): void {
        // Mock registry methods to return empty, letting the real service execute cleanly
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        $result = $this->service->getMetadata();

        expect($result)->toHaveKeys(['cart', 'product']);
        expect($result['cart'])->toHaveKeys(['conditions', 'actions']);
        expect($result['product'])->toHaveKeys(['conditions', 'actions']);
        expect($result['cart']['conditions'])->toBeArray();
        expect($result['cart']['actions'])->toBeArray();
        expect($result['product']['conditions'])->toBeArray();
        expect($result['product']['actions'])->toBeArray();
    });

    it('returns correct metadata for handlers with config', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn(['cart_key' => 'CartCondClass']);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn(['prod_key' => 'ProdCondClass']);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn(['cart_act' => 'CartActClass']);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn(['prod_act' => 'ProdActClass']);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([
            'CartCondClass' => 'CartCondConfig',
            'ProdCondClass' => 'ProdCondConfig',
            'CartActClass'  => 'CartActConfig',
            'ProdActClass'  => 'ProdActConfig',
        ]);

        // Mock config classes
        if (! class_exists('CartCondConfig')) {
            eval('class CartCondConfig { public function __construct(int $foo, float $float, array $array,bool $bool,  string $bar = "baz") {} }');
        }
        if (! class_exists('ProdCondConfig')) {
            eval('class ProdCondConfig { public function __construct() {} }');
        }
        if (! class_exists('CartActConfig')) {
            eval('class CartActConfig { public function __construct() {} }');
        }
        if (! class_exists('ProdActConfig')) {
            eval('class ProdActConfig { public function __construct() {} }');
        }

        $result = $this->service->getMetadata();
        expect($result['cart']['conditions'][0]['key'])->toBe('cart_key');
        expect($result['cart']['conditions'][0]['configuration_schema'])->toHaveKey('foo');
        expect($result['cart']['conditions'][0]['configuration_schema']['foo']['type'])->toBe('integer');
        expect($result['cart']['conditions'][0]['configuration_schema']['bar']['default'])->toBe('baz');
        expect($result['product']['conditions'][0]['key'])->toBe('prod_key');
        expect($result['cart']['actions'][0]['key'])->toBe('cart_act');
        expect($result['product']['actions'][0]['key'])->toBe('prod_act');
    });

    it('getOperators returns all operators', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $result = $this->service->getOperators();
        expect($result)->toBeArray();
        expect($result)->toContain(['value' => 'greater_than', 'label' => __('discount.operators.greater_than'), 'symbol' => '>']);
    });

    it('getTypes returns all types', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $result = $this->service->getTypes();
        expect($result)->toBeArray();
        expect($result[0]['value'])->toBe('product_specific');
        expect($result[1]['value'])->toBe('cart_checkout');
    });

    it('extractConfigSchema returns empty for non-existent class', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $result = $this->service->extractConfigSchema('NonExistentClass', 'anykey');
        expect($result)->toBe([]);
    });

    it('extractConfigSchema returns empty for class with no constructor', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        if (! class_exists('NoCtorClass')) {
            eval('class NoCtorClass {}');
        }
        $result = $this->service->extractConfigSchema('NoCtorClass', 'anykey');
        expect($result)->toBe([]);
    });

    it('extractConfigSchema uses custom descriptions', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        if (! class_exists('CustomDescConfig')) {
            eval('class CustomDescConfig { public static function descriptions() { return ["foo" => "Custom Foo Desc"]; } public function __construct(int $foo) {} }');
        }
        $result = $this->service->extractConfigSchema('CustomDescConfig', 'anykey');
        expect($result['foo']['description'])->toBe('Custom Foo Desc');
    });

    it('extractConfigSchema handles enums and default values', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        if (! enum_exists('TestEnum')) {
            eval('enum TestEnum: string { case A = "a"; case B = "b"; }');
        }
        if (! class_exists('EnumConfig')) {
            eval('class EnumConfig { public function __construct(TestEnum $type = TestEnum::A) {} }');
        }
        $result = $this->service->extractConfigSchema('EnumConfig', 'anykey');
        expect($result['type']['type'])->toBe('enum');
        expect($result['type']['default']->value ?? null)->toBe('a');
        expect($result['type']['cases'])->toBe([
            [
                'value' => 'a',
                'label' => 'A',
            ],
            [
                'value' => 'b',
                'label' => 'B',
            ],
        ]);
    });

    it('getConfigurationClass returns config class if handler exists and method present', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $mockHandler = new class
        {
            public static function getConfigClass(): string
            {
                return 'SomeConfigClass';
            }
        };
        $this->mockRegistry->shouldReceive('getHandlerClassByKey')->andReturn(get_class($mockHandler));
        $result = $this->service->getConfigurationClass('key', 'type', DiscountTypeEnum::CART_CHECKOUT);
        expect($result)->toBe('SomeConfigClass');
    });

    it('getConfigurationClass returns null if handler not found', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerClassByKey')->andReturn(null);
        $result = $this->service->getConfigurationClass('key', 'type', DiscountTypeEnum::CART_CHECKOUT);
        expect($result)->toBeNull();
    });

    it('getConfigurationClass returns null if getConfigClass throws', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $mockHandler = new class
        {
            public static function getConfigClass()
            {
                throw new Exception('fail');
            }
        };
        $this->mockRegistry->shouldReceive('getHandlerClassByKey')->andReturn(get_class($mockHandler));
        $result = $this->service->getConfigurationClass('key', 'type', DiscountTypeEnum::CART_CHECKOUT);
        expect($result)->toBeNull();
    });

    it('getConfigurationClass returns null if getConfigClass not present', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $mockHandler = new class {};
        $this->mockRegistry->shouldReceive('getHandlerClassByKey')->andReturn(get_class($mockHandler));
        $result = $this->service->getConfigurationClass('key', 'type', DiscountTypeEnum::CART_CHECKOUT);
        expect($result)->toBeNull();
    });

    it('getParameterType returns correct types', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        $ref         = new ReflectionClass('CartCondConfig');
        $params      = $ref->getConstructor()->getParameters();
        $typeInt     = $this->service->getParameterType($params[0]->getType());
        $typeFloat   = $this->service->getParameterType($params[1]->getType());
        $typeArray   = $this->service->getParameterType($params[2]->getType());
        $typeBoolean = $this->service->getParameterType($params[3]->getType());
        $typeString  = $this->service->getParameterType($params[4]->getType());
        expect($typeFloat)->toBe('number');
        expect($typeArray)->toBe('array');
        expect($typeInt)->toBe('integer');
        expect($typeBoolean)->toBe('boolean');
        expect($typeString)->toBe('string');
        expect($this->service->getParameterType(null))->toBe('mixed');
    });

    it('extractConfigSchema generates default field descriptions from parameters', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        if (! class_exists('CartCondConfig')) {
            eval('class CartCondConfig { public function __construct(int $foo, float $float, array $array,bool $bool,  string $bar = "baz") {} }');
        }
        $result = $this->service->extractConfigSchema('CartCondConfig', 'cart_key');
        expect($result['foo']['description'])->toBe('Foo (integer)');
    });

    it('resolves handler name using Lang if available, else falls back to humanized key', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([
            'special_key'  => 'SomeClass',
            'fallback_key' => 'SomeClass'
        ]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        // Dynamically add localized line for the "special_key"
        app('translator')->addLines(['discount.handlers.special_key.name' => 'Localized Name'], 'en');
        app()->setLocale('en');

        $conditions = $this->service->getConditions();

        // Assert localized value worked
        expect($conditions['cart'][0]['name'])->toBe('Localized Name');
        // Assert humanized fallback worked for keys without translation entries
        expect($conditions['cart'][1]['name'])->toBe('Fallback Key');
    });

    it('resolves handler description using Lang if available, else falls back to humanized class name', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([
            'special_key'  => 'SomeHandlerClass',
            'fallback_key' => 'SomeHandlerCondition'
        ]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        // Dynamically add localized line for the "special_key"
        app('translator')->addLines(['discount.handlers.special_key.description' => 'Localized Desc'], 'en');
        app()->setLocale('en');

        $conditions = $this->service->getConditions();

        // Assert localized value worked
        expect($conditions['cart'][0]['description'])->toBe('Localized Desc');
        // Assert humanized fallback worked for classes without translation entries
        expect($conditions['cart'][1]['description'])->toBe('Some Handler');
    });

    it('extractConfigSchema handles enums with AdvanceEnum trait', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);
        if (! enum_exists('AdvEnum')) {
            eval('enum AdvEnum: string { use \App\Traits\AdvanceEnum; case A = "case1"; case B = "case2"; }');
        }
        if (! class_exists('AdvEnumConfig')) {
            eval('class AdvEnumConfig { public function __construct(AdvEnum $type) {} }');
        }
        $result = $this->service->extractConfigSchema('AdvEnumConfig', 'anykey');
        expect($result['type']['cases'])->toBe(
            [
                ['value' => 'case1', 'label' => 'enums.AdvEnum.case1'],
                ['value' => 'case2', 'label' => 'enums.AdvEnum.case2'],
            ]
        );
    });

    it('getParameterType handles non-ReflectionNamedType (e.g., ReflectionUnionType)', function (): void {
        $mockType = Mockery::mock(ReflectionType::class);
        $mockType->shouldReceive('__toString')->andReturn('int|string');
        $result = $this->service->getParameterType($mockType);
        expect($result)->toBe('int|string');
    });

    it('getParameterType returns type name for custom class (default branch)', function (): void {
        if (! class_exists('CustomType')) {
            eval('class CustomType {}');
        }
        if (! class_exists('DummyCustomType')) {
            eval('class DummyCustomType { public function __construct(CustomType $foo) {} }');
        }
        $ref    = new ReflectionClass('DummyCustomType');
        $params = $ref->getConstructor()->getParameters();
        $type   = $params[0]->getType();
        $result = $this->service->getParameterType($type);
        expect($result)->toBe('CustomType');
    });
});
