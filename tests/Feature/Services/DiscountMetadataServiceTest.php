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
            public static function getConfigClass(): never
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
            'fallback_key' => 'SomeClass',
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
            'fallback_key' => 'SomeHandlerCondition',
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

    it('detects array-of-model fields via exists: rule and emits model_reference', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! class_exists('ArrayModelConfig')) {
            eval('
                final class ArrayModelConfig {
                    public function __construct(
                        public array $product_ids
                    ) {}
                    public static function rules(): array {
                        return [
                            "product_ids"   => ["required", "array"],
                            "product_ids.*" => ["integer", "exists:products,id"],
                        ];
                    }
                    public static function fieldMeta(): array {
                        return [
                            "product_ids" => [
                                "item" => [
                                    "item_type" => "model",
                                    "model_reference" => [
                                        "table"          => "products",
                                        "column"         => "id",
                                        "display_column" => "name",
                                    ],
                                ],
                            ],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('ArrayModelConfig', 'anykey');
        expect($schema['product_ids']['type'])->toBe('array')
            ->and($schema['product_ids']['item_type'])->toBe('model')
            ->and($schema['product_ids']['model_reference'])
            ->toMatchArray([
                'table'          => 'products',
                'column'         => 'id',
                'display_column' => 'name',
            ]);
    });

    it('auto-detects array-of-model fields from rules without fieldMeta override', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! class_exists('AutoModelConfig')) {
            eval('
                final class AutoModelConfig {
                    public function __construct(
                        public array $vendor_ids
                    ) {}
                    public static function rules(): array {
                        return [
                            "vendor_ids"   => ["required", "array"],
                            "vendor_ids.*" => ["integer", "exists:vendors,id"],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('AutoModelConfig', 'anykey');
        expect($schema['vendor_ids']['item_type'])->toBe('model')
            ->and($schema['vendor_ids']['model_reference'])
            ->toMatchArray(['table' => 'vendors', 'column' => 'id']);
    });

    it('detects single FK model_reference from exists: rule on scalar field', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! class_exists('SingleFkConfig')) {
            eval('
                final class SingleFkConfig {
                    public function __construct(
                        public int $product_delivery_option_id
                    ) {}
                    public static function rules(): array {
                        return [
                            "product_delivery_option_id" => ["required", "integer", "exists:product_delivery_options,id"],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('SingleFkConfig', 'anykey');
        expect($schema['product_delivery_option_id']['type'])->toBe('integer')
            ->and($schema['product_delivery_option_id']['model_reference'])
            ->toMatchArray(['table' => 'product_delivery_options', 'column' => 'id']);
    });

    it('detects array-of-enum fields and emits item_enum + item_cases', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! enum_exists('ItemEnumTest')) {
            eval('enum ItemEnumTest: string { case A = "a"; case B = "b"; }');
        }
        if (! class_exists('ArrayEnumConfig')) {
            eval('
                final class ArrayEnumConfig {
                    public function __construct(
                        public array $methods
                    ) {}
                    public static function rules(): array {
                        return [
                            "methods"   => ["required", "array"],
                            "methods.*" => ["required", \Illuminate\Validation\Rule::enum("ItemEnumTest")],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('ArrayEnumConfig', 'anykey');
        expect($schema['methods']['item_type'])->toBe('enum')
            ->and($schema['methods']['item_enum'])->toBe('ItemEnumTest')
            ->and($schema['methods']['item_cases'])->toBe([
                ['value' => 'a', 'label' => 'A'],
                ['value' => 'b', 'label' => 'B'],
            ]);
    });

    it('detects array-of-Data (nested) via fieldMeta and recurses item_schema', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! class_exists('NestedTierItemData')) {
            eval('
                final class NestedTierItemData extends \Spatie\LaravelData\Data {
                    public function __construct(
                        public int $min_amount,
                        public float $percentage
                    ) {}
                    public static function rules(): array {
                        return [
                            "min_amount" => ["required", "integer", "min:0"],
                            "percentage" => ["required", "numeric", "min:0", "max:100"],
                        ];
                    }
                }
            ');
        }
        if (! class_exists('NestedDataConfig')) {
            eval('
                final class NestedDataConfig {
                    public function __construct(
                        public array $tiers
                    ) {}
                    public static function rules(): array {
                        return [
                            "tiers" => ["required", "array"],
                        ];
                    }
                    public static function fieldMeta(): array {
                        return [
                            "tiers" => [
                                "item" => [
                                    "item_type"  => "data",
                                    "item_class" => "NestedTierItemData",
                                ],
                            ],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('NestedDataConfig', 'anykey');
        expect($schema['tiers']['item_type'])->toBe('data')
            ->and($schema['tiers']['item_class'])->toBe('NestedTierItemData')
            ->and($schema['tiers']['item_schema'])->toBeArray()
            ->and($schema['tiers']['item_schema'])->toHaveKeys(['min_amount', 'percentage'])
            ->and($schema['tiers']['item_schema']['min_amount']['type'])->toBe('integer')
            ->and($schema['tiers']['item_schema']['percentage']['type'])->toBe('number');
    });

    it('extractConfigSchema prevents infinite recursion on self-referencing Data', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! class_exists('RecursiveConfig')) {
            eval('
                final class RecursiveConfig extends \Spatie\LaravelData\Data {
                    public function __construct(
                        public array $children
                    ) {}
                    public static function rules(): array {
                        return [
                            "children" => ["required", "array"],
                        ];
                    }
                    public static function fieldMeta(): array {
                        return [
                            "children" => [
                                "item" => [
                                    "item_type"  => "data",
                                    "item_class" => "RecursiveConfig",
                                ],
                            ],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('RecursiveConfig', 'anykey');
        expect($schema['children']['item_type'])->toBe('data')
            ->and($schema['children']['item_class'])->toBe('RecursiveConfig')
            ->and($schema['children']['item_schema'])->toBe([]);
    });

    it('fieldMeta override for display_column wins over rules-only detection', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! class_exists('OverrideDisplayConfig')) {
            eval('
                final class OverrideDisplayConfig {
                    public function __construct(
                        public array $sku_ids
                    ) {}
                    public static function rules(): array {
                        return [
                            "sku_ids"   => ["required", "array"],
                            "sku_ids.*" => ["integer", "exists:product_delivery_options,id"],
                        ];
                    }
                    public static function fieldMeta(): array {
                        return [
                            "sku_ids" => [
                                "item" => [
                                    "item_type" => "model",
                                    "model_reference" => [
                                        "table"          => "product_delivery_options",
                                        "column"         => "id",
                                        "display_column" => "sku",
                                    ],
                                ],
                            ],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('OverrideDisplayConfig', 'anykey');
        expect($schema['sku_ids']['model_reference']['display_column'])->toBe('sku');
    });

    it('array of scalar strings yields item_type string', function (): void {
        $this->mockRegistry->shouldReceive('getCartConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductConditionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getCartActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getProductActionHandlers')->andReturn([]);
        $this->mockRegistry->shouldReceive('getHandlerConfigMap')->andReturn([]);

        if (! class_exists('ScalarArrayConfig')) {
            eval('
                final class ScalarArrayConfig {
                    public function __construct(
                        public array $tags
                    ) {}
                    public static function rules(): array {
                        return [
                            "tags"   => ["required", "array"],
                            "tags.*" => ["string"],
                        ];
                    }
                }
            ');
        }

        $schema = $this->service->extractConfigSchema('ScalarArrayConfig', 'anykey');
        expect($schema['tags']['item_type'])->toBe('string');
    });
});
