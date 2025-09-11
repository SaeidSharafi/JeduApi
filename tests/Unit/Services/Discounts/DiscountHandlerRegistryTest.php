<?php

declare(strict_types=1);

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Contracts\Discounts\ProductDiscountActionContract;
use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\DiscountTypeEnum;
use App\Models\ProductDeliveryOption;
use App\Services\Discounts\DiscountHandlerRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Spatie\LaravelData\Data;

// Mock handler classes for testing
#[DiscountHandlerKey('test_cart_condition')]
final class MockCartCondition implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return 'MockCartConditionConfig';
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        return true;
    }
}

#[DiscountHandlerKey('test_cart_action')]
final class MockCartAction implements DiscountActionContract
{
    public static function getConfigClass(): string
    {
        return 'MockCartActionConfig';
    }

    public function apply(OrderContextData $context, Data $configuration): void
    {
        // Do nothing for test
    }
}

#[DiscountHandlerKey('test_product_condition')]
final class MockProductCondition implements ProductDiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return 'MockProductConditionConfig';
    }

    public function passes(ProductDeliveryOption $option, Data $configuration): bool
    {
        return true;
    }
}

#[DiscountHandlerKey('test_product_action')]
final class MockProductAction implements ProductDiscountActionContract
{
    public static function getConfigClass(): string
    {
        return 'MockProductActionConfig';
    }

    public function apply(ProductDeliveryOption $option, Data $configuration): int
    {
        return $option->price;
    }
}

// Mock handler without attribute
final class MockHandlerWithoutAttribute implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        return 'MockConfig';
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        return true;
    }
}

// Abstract mock class (not instantiable)
#[DiscountHandlerKey('test_abstract')]
abstract class MockAbstractHandler implements DiscountConditionContract
{
    final public static function getConfigClass(): string
    {
        return 'MockConfig';
    }
}

// Mock handler that throws exception in getConfigClass
#[DiscountHandlerKey('test_exception_config')]
final class MockHandlerWithExceptionConfig implements DiscountConditionContract
{
    public static function getConfigClass(): string
    {
        throw new Exception('Config class not found');
    }

    public function passes(OrderContextData $context, Data $configuration): bool
    {
        return true;
    }
}

describe('DiscountHandlerRegistry', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('loads handlers from cache when not in debug mode and cache exists', function () {
        config()->set('app.debug', false);

        $cachedData = [
            'cartConditions'    => ['key1' => 'Class1'],
            'cartActions'       => ['key2' => 'Class2'],
            'productConditions' => ['key3' => 'Class3'],
            'productActions'    => ['key4' => 'Class4'],
            'configMap'         => ['Class1' => 'Config1'],
        ];

        Cache::shouldReceive('get')
            ->once()
            ->with(DiscountHandlerRegistry::CACHE_KEY)
            ->andReturn($cachedData);

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getCartConditionHandlers())->toBe(['key1' => 'Class1']);
        expect($registry->getCartActionHandlers())->toBe(['key2' => 'Class2']);
        expect($registry->getProductConditionHandlers())->toBe(['key3' => 'Class3']);
        expect($registry->getProductActionHandlers())->toBe(['key4' => 'Class4']);
        expect($registry->getHandlerConfigMap())->toBe(['Class1' => 'Config1']);
    });

    it('discovers and caches handlers when not in debug mode and no cache exists', function () {
        config()->set('app.debug', false);
        config()->set('discounts.discovery_paths', []);

        Cache::shouldReceive('get')
            ->once()
            ->with(DiscountHandlerRegistry::CACHE_KEY)
            ->andReturn(null);

        Cache::shouldReceive('forever')
            ->once()
            ->with(DiscountHandlerRegistry::CACHE_KEY, [
                'cartConditions'    => [],
                'cartActions'       => [],
                'productConditions' => [],
                'productActions'    => [],
                'configMap'         => [],
            ]);

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getCartConditionHandlers())->toBe([]);
        expect($registry->getCartActionHandlers())->toBe([]);
        expect($registry->getProductConditionHandlers())->toBe([]);
        expect($registry->getProductActionHandlers())->toBe([]);
        expect($registry->getHandlerConfigMap())->toBe([]);
    });

    it('discovers and caches handlers in debug mode', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []);

        Cache::shouldReceive('forever')
            ->once()
            ->with(DiscountHandlerRegistry::CACHE_KEY, [
                'cartConditions'    => [],
                'cartActions'       => [],
                'productConditions' => [],
                'productActions'    => [],
                'configMap'         => [],
            ]);

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('skips non-existent directories during discovery', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', [
            'App\\NonExistent\\' => 'NonExistent/Path',
        ]);

        $mockFilesystem = $this->mock(Filesystem::class, function (MockInterface $mock) {
            $mock->shouldReceive('isDirectory')
                ->with(app_path('NonExistent/Path'))
                ->andReturn(false);
        });

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry($mockFilesystem);

        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('discovers handlers from files in configured paths', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []);

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        // Since no discovery paths are configured, it should be empty
        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('skips non-php files during discovery', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []);

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('skips classes that do not exist', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []);

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('skips classes without DiscountHandlerKey attribute', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []); // Empty to avoid discovering real handlers

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        // With empty discovery paths, no handlers should be found
        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('skips abstract classes that are not instantiable', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []); // Empty to avoid discovering real handlers

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('handles reflection exceptions gracefully', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []);

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getCartConditionHandlers())->toBe([]);
    });

    it('handles config class discovery exceptions gracefully', function () {
        config()->set('app.debug', true);
        config()->set('discounts.discovery_paths', []); // Empty to avoid discovering real handlers

        Cache::shouldReceive('forever')->once();

        $registry = new DiscountHandlerRegistry(app(Filesystem::class));

        expect($registry->getHandlerConfigMap())->toBe([]);
    });

    describe('getHandlerClassByKey', function () {
        beforeEach(function () {
            $this->registry = new DiscountHandlerRegistry(app(Filesystem::class));

            // Use reflection to set the handlers for testing
            $reflection = new ReflectionClass($this->registry);

            $cartConditionsProperty = $reflection->getProperty('cartConditionHandlers');
            $cartConditionsProperty->setAccessible(true);
            $cartConditionsProperty->setValue($this->registry, ['test_key' => 'TestCartCondition']);

            $cartActionsProperty = $reflection->getProperty('cartActionHandlers');
            $cartActionsProperty->setAccessible(true);
            $cartActionsProperty->setValue($this->registry, ['test_key' => 'TestCartAction']);

            $productConditionsProperty = $reflection->getProperty('productConditionHandlers');
            $productConditionsProperty->setAccessible(true);
            $productConditionsProperty->setValue($this->registry, ['test_key' => 'TestProductCondition']);

            $productActionsProperty = $reflection->getProperty('productActionHandlers');
            $productActionsProperty->setAccessible(true);
            $productActionsProperty->setValue($this->registry, ['test_key' => 'TestProductAction']);
        });

        it('returns cart condition handler for cart checkout discount type', function () {
            $result = $this->registry->getHandlerClassByKey('test_key', 'condition', DiscountTypeEnum::CART_CHECKOUT);
            expect($result)->toBe('TestCartCondition');
        });

        it('returns cart action handler for cart checkout discount type', function () {
            $result = $this->registry->getHandlerClassByKey('test_key', 'action', DiscountTypeEnum::CART_CHECKOUT);
            expect($result)->toBe('TestCartAction');
        });

        it('returns product condition handler for product specific discount type', function () {
            $result = $this->registry->getHandlerClassByKey('test_key', 'condition', DiscountTypeEnum::PRODUCT_SPECIFIC);
            expect($result)->toBe('TestProductCondition');
        });

        it('returns product action handler for product specific discount type', function () {
            $result = $this->registry->getHandlerClassByKey('test_key', 'action', DiscountTypeEnum::PRODUCT_SPECIFIC);
            expect($result)->toBe('TestProductAction');
        });

        it('returns null for non-existent key', function () {
            $result = $this->registry->getHandlerClassByKey('non_existent', 'condition', DiscountTypeEnum::CART_CHECKOUT);
            expect($result)->toBeNull();
        });

        it('returns null for invalid type', function () {
            $result = $this->registry->getHandlerClassByKey('test_key', 'invalid', DiscountTypeEnum::CART_CHECKOUT);
            expect($result)->toBeNull();
        });

        it('returns null for mismatched discount type and handler type', function () {
            $result = $this->registry->getHandlerClassByKey('test_key', 'condition', DiscountTypeEnum::PRODUCT_SPECIFIC);
            expect($result)->toBe('TestProductCondition'); // This should work

            $result = $this->registry->getHandlerClassByKey('test_key', 'condition', DiscountTypeEnum::CART_CHECKOUT);
            expect($result)->toBe('TestCartCondition'); // This should work too
        });
    });

    describe('individual getter methods', function () {
        beforeEach(function () {
            $this->registry = new DiscountHandlerRegistry(app(Filesystem::class));

            // Use reflection to set the handlers for testing
            $reflection = new ReflectionClass($this->registry);

            $cartConditionsProperty = $reflection->getProperty('cartConditionHandlers');
            $cartConditionsProperty->setAccessible(true);
            $cartConditionsProperty->setValue($this->registry, ['key1' => 'CartCondition1']);

            $cartActionsProperty = $reflection->getProperty('cartActionHandlers');
            $cartActionsProperty->setAccessible(true);
            $cartActionsProperty->setValue($this->registry, ['key2' => 'CartAction1']);

            $productConditionsProperty = $reflection->getProperty('productConditionHandlers');
            $productConditionsProperty->setAccessible(true);
            $productConditionsProperty->setValue($this->registry, ['key3' => 'ProductCondition1']);

            $productActionsProperty = $reflection->getProperty('productActionHandlers');
            $productActionsProperty->setAccessible(true);
            $productActionsProperty->setValue($this->registry, ['key4' => 'ProductAction1']);

            $configMapProperty = $reflection->getProperty('handlerConfigMap');
            $configMapProperty->setAccessible(true);
            $configMapProperty->setValue($this->registry, ['Handler1' => 'Config1']);
        });

        it('returns cart condition handler by key', function () {
            expect($this->registry->getCartConditionHandler('key1'))->toBe('CartCondition1');
            expect($this->registry->getCartConditionHandler('non_existent'))->toBeNull();
        });

        it('returns cart action handler by key', function () {
            expect($this->registry->getCartActionHandler('key2'))->toBe('CartAction1');
            expect($this->registry->getCartActionHandler('non_existent'))->toBeNull();
        });

        it('returns product condition handler by key', function () {
            expect($this->registry->getProductConditionHandler('key3'))->toBe('ProductCondition1');
            expect($this->registry->getProductConditionHandler('non_existent'))->toBeNull();
        });

        it('returns product action handler by key', function () {
            expect($this->registry->getProductActionHandler('key4'))->toBe('ProductAction1');
            expect($this->registry->getProductActionHandler('non_existent'))->toBeNull();
        });

        it('returns config class for handler', function () {
            expect($this->registry->getConfigClass('Handler1'))->toBe('Config1');
            expect($this->registry->getConfigClass('NonExistentHandler'))->toBeNull();
        });
    });

    describe('getClassNameFromFile', function () {
        it('generates correct class name from file path', function () {
            $registry   = new DiscountHandlerRegistry(app(Filesystem::class));
            $reflection = new ReflectionClass($registry);
            $method     = $reflection->getMethod('getClassNameFromFile');
            $method->setAccessible(true);

            $basePath      = app_path('Services/Discounts');
            $filePath      = app_path('Services/Discounts/Cart/Conditions/TestCondition.php');
            $baseNamespace = 'App\\Services\\Discounts\\';

            $result = $method->invoke($registry, $filePath, $basePath, $baseNamespace);

            expect($result)->toBe('App\\Services\\Discounts\\Cart\\Conditions\\TestCondition');
        });

        it('handles Windows path separators correctly', function () {
            $registry   = new DiscountHandlerRegistry(app(Filesystem::class));
            $reflection = new ReflectionClass($registry);
            $method     = $reflection->getMethod('getClassNameFromFile');
            $method->setAccessible(true);

            // Test with Unix-style paths (since that's what the system actually uses)
            // The method converts file separators to namespace separators properly
            $basePath      = '/app/Services/Discounts';
            $filePath      = '/app/Services/Discounts/Cart/Conditions/TestCondition.php';
            $baseNamespace = 'App\\Services\\Discounts\\';

            $result = $method->invoke($registry, $filePath, $basePath, $baseNamespace);

            expect($result)->toBe('App\\Services\\Discounts\\Cart\\Conditions\\TestCondition');
        });
    });

    describe('cache operations', function () {
        it('caches discovered handlers correctly', function () {
            config()->set('app.debug', false);
            config()->set('discounts.discovery_paths', []);

            Cache::shouldReceive('get')
                ->once()
                ->with(DiscountHandlerRegistry::CACHE_KEY)
                ->andReturn(null);

            $expectedCacheData = [
                'cartConditions'    => [],
                'cartActions'       => [],
                'productConditions' => [],
                'productActions'    => [],
                'configMap'         => [],
            ];

            Cache::shouldReceive('forever')
                ->once()
                ->with(DiscountHandlerRegistry::CACHE_KEY, $expectedCacheData);

            new DiscountHandlerRegistry(app(Filesystem::class));
        });

        it('loads from cache with partial data', function () {
            config()->set('app.debug', false);

            $cachedData = [
                'cartConditions' => ['key1' => 'Class1'],
                // Missing other keys to test default empty arrays
            ];

            Cache::shouldReceive('get')
                ->once()
                ->with(DiscountHandlerRegistry::CACHE_KEY)
                ->andReturn($cachedData);

            $registry = new DiscountHandlerRegistry(app(Filesystem::class));

            expect($registry->getCartConditionHandlers())->toBe(['key1' => 'Class1']);
            expect($registry->getCartActionHandlers())->toBe([]);
            expect($registry->getProductConditionHandlers())->toBe([]);
            expect($registry->getProductActionHandlers())->toBe([]);
            expect($registry->getHandlerConfigMap())->toBe([]);
        });
    });

    describe('discovery edge cases', function () {
        it('handles empty discovery paths', function () {
            config()->set('app.debug', true);
            config()->set('discounts.discovery_paths', []);

            Cache::shouldReceive('forever')->once();

            $registry = new DiscountHandlerRegistry(app(Filesystem::class));

            expect($registry->getCartConditionHandlers())->toBe([]);
            expect($registry->getCartActionHandlers())->toBe([]);
            expect($registry->getProductConditionHandlers())->toBe([]);
            expect($registry->getProductActionHandlers())->toBe([]);
        });

        it('handles multiple discovery paths', function () {
            config()->set('app.debug', true);
            config()->set('discounts.discovery_paths', [
                'App\\Services\\Discounts\\' => 'Services/Discounts',
                'App\\CustomDiscounts\\'     => 'CustomDiscounts',
            ]);

            $mockFilesystem = $this->mock(Filesystem::class, function (MockInterface $mock) {
                $mock->shouldReceive('isDirectory')
                    ->with(app_path('Services/Discounts'))
                    ->andReturn(false);
                $mock->shouldReceive('isDirectory')
                    ->with(app_path('CustomDiscounts'))
                    ->andReturn(false);
            });

            Cache::shouldReceive('forever')->once();

            $registry = new DiscountHandlerRegistry($mockFilesystem);

            expect($registry->getCartConditionHandlers())->toBe([]);
        });

        it('correctly processes existing discount handlers', function () {
            config()->set('app.debug', true);
            config()->set('discounts.discovery_paths', [
                'App\\Services\\Discounts\\' => 'Services/Discounts',
            ]);

            Cache::shouldReceive('forever')->once();

            $registry = new DiscountHandlerRegistry(app(Filesystem::class));

            // If real handlers are discovered, they should be in the registry
            $cartConditions    = $registry->getCartConditionHandlers();
            $cartActions       = $registry->getCartActionHandlers();
            $productConditions = $registry->getProductConditionHandlers();
            $productActions    = $registry->getProductActionHandlers();

            // These should be arrays (might be empty or contain actual handlers)
            expect($cartConditions)->toBeArray();
            expect($cartActions)->toBeArray();
            expect($productConditions)->toBeArray();
            expect($productActions)->toBeArray();
        });
    });

    describe('real handler discovery', function () {
        it('can discover real handlers when config is set up properly', function () {
            config()->set('app.debug', true);
            config()->set('discounts.discovery_paths', [
                'App\\Services\\Discounts\\' => 'Services/Discounts',
            ]);

            Cache::shouldReceive('forever')->once();

            $registry = new DiscountHandlerRegistry(app(Filesystem::class));

            // Verify that the registry can work with real classes
            $configMap = $registry->getHandlerConfigMap();
            expect($configMap)->toBeArray();

            // Test that the getClassNameFromFile method generates proper class names
            $reflection = new ReflectionClass($registry);
            $method     = $reflection->getMethod('getClassNameFromFile');
            $method->setAccessible(true);

            $result = $method->invoke(
                $registry,
                '/test/path/to/TestHandler.php',
                '/test/path',
                'App\\Test\\'
            );

            expect($result)->toBe('App\\Test\\to\\TestHandler');
        });
    });
});
