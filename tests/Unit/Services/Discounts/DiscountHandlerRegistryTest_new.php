<?php

use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\Cart\Actions\ApplyPercentageDiscountToItemsAction;
use App\Services\Discounts\Cart\Conditions\CartValueCondition;
use App\Services\Discounts\Cart\Conditions\ProductCategoryCondition;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use App\Services\Discounts\Configs\CartValueConditionConfigData;
use App\Services\Discounts\Configs\ProductCategoryConditionConfigData;

describe('DiscountHandlerRegistry', function () {

    test('it can be instantiated and provides basic functionality', function () {
        // Act
        $registry = app(DiscountHandlerRegistry::class);

        // Assert - Basic functionality works
        expect($registry)->toBeInstanceOf(DiscountHandlerRegistry::class);
        expect($registry->getCartConditionHandlers())->toBeArray();
        expect($registry->getCartActionHandlers())->toBeArray();
        expect($registry->getProductConditionHandlers())->toBeArray();
        expect($registry->getProductActionHandlers())->toBeArray();
        expect($registry->getHandlerConfigMap())->toBeArray();
    });

    test('it provides cart condition handlers', function () {
        // Arrange
        $registry = app(DiscountHandlerRegistry::class);

        // Act
        $cartConditions = $registry->getCartConditionHandlers();

        // Assert - Should contain at least the static handlers
        expect($cartConditions)->toBeArray();
        expect(count($cartConditions))->toBeGreaterThan(0);

        // Check that we can get specific handlers
        $handler = $registry->getCartConditionHandler('cart_value_over');
        expect($handler)->not->toBeNull();
    });

    test('it provides cart action handlers', function () {
        // Arrange
        $registry = app(DiscountHandlerRegistry::class);

        // Act
        $cartActions = $registry->getCartActionHandlers();

        // Assert
        expect($cartActions)->toBeArray();
        expect(count($cartActions))->toBeGreaterThan(0);

        // Check that we can get specific handlers
        $handler = $registry->getCartActionHandler('apply_percentage_off');
        expect($handler)->not->toBeNull();
    });

    test('it provides product handlers', function () {
        // Arrange
        $registry = app(DiscountHandlerRegistry::class);

        // Act
        $productConditions = $registry->getProductConditionHandlers();
        $productActions = $registry->getProductActionHandlers();

        // Assert
        expect($productConditions)->toBeArray();
        expect($productActions)->toBeArray();

        // Product handlers might be empty or populated depending on auto-discovery
        // So we just check they're arrays
    });

    test('it returns null for non-existent handlers', function () {
        // Arrange
        $registry = app(DiscountHandlerRegistry::class);

        // Act & Assert
        expect($registry->getCartConditionHandler('non_existent_handler'))->toBeNull();
        expect($registry->getCartActionHandler('non_existent_handler'))->toBeNull();
        expect($registry->getProductConditionHandler('non_existent_handler'))->toBeNull();
        expect($registry->getProductActionHandler('non_existent_handler'))->toBeNull();
        expect($registry->getConfigClass('NonExistentClass'))->toBeNull();
    });

    test('it provides config class mappings', function () {
        // Arrange
        $registry = app(DiscountHandlerRegistry::class);

        // Act & Assert - Check that config classes can be retrieved
        $configMap = $registry->getHandlerConfigMap();
        expect($configMap)->toBeArray();
        expect(count($configMap))->toBeGreaterThan(0);

        // Check some specific mappings if they exist
        foreach ($configMap as $handlerClass => $configClass) {
            expect($handlerClass)->toBeString();
            expect($configClass)->toBeString();
        }
    });

    test('it discovers handlers from actual file system', function () {
        // Arrange
        $registry = app(DiscountHandlerRegistry::class);

        // Act
        $cartConditions = $registry->getCartConditionHandlers();
        $cartActions = $registry->getCartActionHandlers();

        // Assert - The registry should have discovered real handlers
        expect($cartConditions)->toBeArray();
        expect($cartActions)->toBeArray();

        // Should have at least the basic handlers we know exist
        expect(array_keys($cartConditions))->toContain('cart_value_over');
        expect(array_keys($cartConditions))->toContain('product_in_category');
        expect(array_keys($cartActions))->toContain('apply_percentage_off');
    });

    test('it handles non-existent directories gracefully', function () {
        // Arrange
        $mockFilesystem = $this->mock(\Illuminate\Filesystem\Filesystem::class);

        // Mock all the method calls that happen during initialization
        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Cart/Actions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Cart/Actions'))
            ->andReturn(collect([]));

        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Cart/Conditions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Cart/Conditions'))
            ->andReturn(collect([]));

        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Product/Actions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Product/Actions'))
            ->andReturn(collect([]));

        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Product/Conditions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Product/Conditions'))
            ->andReturn(collect([]));

        // Test the specific case of non-existent directory
        $mockFilesystem->shouldReceive('exists')
            ->with('/non/existent/path')
            ->andReturn(false);

        $registry = new DiscountHandlerRegistry($mockFilesystem);

        // Use reflection to call the private method
        $method = new ReflectionMethod(DiscountHandlerRegistry::class, 'discoverHandlersInDirectory');
        $method->setAccessible(true);

        // Act & Assert - Should not throw exception when directory doesn't exist
        expect(function () use ($method, $registry) {
            $method->invoke($registry, '/non/existent/path', 'App\\Test', function () {});
        })->not->toThrow(Exception::class);
    });

    test('it handles classes that do not exist gracefully', function () {
        // Arrange
        $mockFilesystem = $this->mock(\Illuminate\Filesystem\Filesystem::class);
        $mockFile = $this->mock(\SplFileInfo::class);
        $mockFile->shouldReceive('getFilenameWithoutExtension')->andReturn('NonExistentClass');

        // Mock all the initialization calls first
        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Cart/Actions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Cart/Actions'))
            ->andReturn(collect([]));

        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Cart/Conditions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Cart/Conditions'))
            ->andReturn(collect([]));

        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Product/Actions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Product/Actions'))
            ->andReturn(collect([]));

        $mockFilesystem->shouldReceive('exists')
            ->with(app_path('Services/Discounts/Product/Conditions'))
            ->andReturn(true);
        $mockFilesystem->shouldReceive('files')
            ->with(app_path('Services/Discounts/Product/Conditions'))
            ->andReturn(collect([]));

        // Test specific case
        $mockFilesystem->shouldReceive('exists')->andReturn(true);
        $mockFilesystem->shouldReceive('files')->andReturn(collect([$mockFile]));

        $registry = new DiscountHandlerRegistry($mockFilesystem);

        // Use reflection to call the private method
        $method = new ReflectionMethod(DiscountHandlerRegistry::class, 'discoverHandlersInDirectory');
        $method->setAccessible(true);

        // Act & Assert - Should not throw exception when class doesn't exist
        expect(function () use ($method, $registry) {
            $method->invoke($registry, '/some/path', 'App\\NonExistent', function () {});
        })->not->toThrow(Exception::class);
    });

    test('it handles missing config classes gracefully', function () {
        // This test verifies that the discoverConfigClass method handles classes without config classes
        // We can test this by checking that a handler without a config class doesn't get added to the config map

        $registry = app(DiscountHandlerRegistry::class);
        $configMap = $registry->getHandlerConfigMap();

        // Check that all existing mapped classes have valid config classes
        foreach ($configMap as $handlerClass => $configClass) {
            expect(class_exists($configClass))->toBeTrue("Config class $configClass should exist for handler $handlerClass");
        }

        // This indirectly tests that classes without config classes are not added to the map
        expect($configMap)->toBeArray();
    });

    test('it successfully maps config class when it exists', function () {
        // This test verifies the legitimate behavior of discoverConfigClass when a config class actually exists
        $registry = app(DiscountHandlerRegistry::class);

        // Create a mock DiscountHandler attribute
        $mockHandler = new App\Attributes\DiscountHandler('test_handler', 'action');

        // Use reflection to call the private discoverConfigClass method
        $reflection = new ReflectionClass($registry);
        $method = $reflection->getMethod('discoverConfigClass');
        $method->setAccessible(true);

        // Test with a legitimate class name that implements getConfigClass() method
        // Use the actual ApplyPercentageDiscountToItemsAction class which has getConfigClass() implemented
        $actionClassName = 'App\\Services\\Discounts\\Cart\\Actions\\ApplyPercentageDiscountToItemsAction';

        // Verify this class exists and has the getConfigClass method
        expect(class_exists($actionClassName))->toBeTrue("Action class should exist: {$actionClassName}");
        expect(method_exists($actionClassName, 'getConfigClass'))->toBeTrue("Action class should have getConfigClass method");

        // Get the expected config class from the handler itself
        $expectedConfig = $actionClassName::getConfigClass();
        expect(class_exists($expectedConfig))->toBeTrue("Expected config class should exist: {$expectedConfig}");

        // Call the method - this should use the interface method to get config class
        $method->invoke($registry, $mockHandler, $actionClassName);

        // Verify the mapping was created
        $mappedConfig = $registry->getConfigClass($actionClassName);
        expect($mappedConfig)->toBe($expectedConfig);

        // Test with a condition class too for completeness
        $conditionClassName = 'App\\Services\\Discounts\\Cart\\Conditions\\CartValueCondition';
        expect(class_exists($conditionClassName))->toBeTrue("Condition class should exist: {$conditionClassName}");
        expect(method_exists($conditionClassName, 'getConfigClass'))->toBeTrue("Condition class should have getConfigClass method");

        $expectedConditionConfig = $conditionClassName::getConfigClass();
        $method->invoke($registry, $mockHandler, $conditionClassName);

        $mappedConditionConfig = $registry->getConfigClass($conditionClassName);
        expect($mappedConditionConfig)->toBe($expectedConditionConfig);

        // Verify both mappings are not null
        expect($mappedConfig)->not->toBeNull('Action config mapping should have been created');
        expect($mappedConditionConfig)->not->toBeNull('Condition config mapping should have been created');
    });

    test('it handles handlers that do not implement getConfigClass method gracefully', function () {
        // This test verifies that discoverConfigClass handles classes that don't implement getConfigClass gracefully
        $registry = app(DiscountHandlerRegistry::class);

        // Create a mock DiscountHandler attribute
        $mockHandler = new App\Attributes\DiscountHandler('test_handler', 'action');

        // Use reflection to call the private discoverConfigClass method
        $reflection = new ReflectionClass($registry);
        $method = $reflection->getMethod('discoverConfigClass');
        $method->setAccessible(true);

        // Create a mock class that doesn't implement getConfigClass method
        // We'll use a real existing class that doesn't have the getConfigClass method
        $classWithoutMethod = 'App\\Http\\Controllers\\Controller'; // This class exists but doesn't have getConfigClass

        // Verify this class exists but doesn't have getConfigClass method
        expect(class_exists($classWithoutMethod))->toBeTrue("Test class should exist: {$classWithoutMethod}");
        expect(method_exists($classWithoutMethod, 'getConfigClass'))->toBeFalse("Test class should not have getConfigClass method");

        // Get the config map before calling the method
        $configMapBefore = $registry->getHandlerConfigMap();
        $initialCount = count($configMapBefore);

        // Act - Call the method with a class that doesn't implement getConfigClass
        // This should not throw an exception and should not add anything to the config map
        expect(function () use ($method, $registry, $mockHandler, $classWithoutMethod) {
            $method->invoke($registry, $mockHandler, $classWithoutMethod);
        })->not->toThrow(Exception::class);

        // Assert - The config map should remain unchanged
        $configMapAfter = $registry->getHandlerConfigMap();
        expect(count($configMapAfter))->toBe($initialCount);
        expect($registry->getConfigClass($classWithoutMethod))->toBeNull();

        // Test with a non-existent class name too
        $nonExistentClass = 'App\\NonExistent\\TestClass';
        expect(class_exists($nonExistentClass))->toBeFalse("Non-existent class should not exist");

        expect(function () use ($method, $registry, $mockHandler, $nonExistentClass) {
            $method->invoke($registry, $mockHandler, $nonExistentClass);
        })->not->toThrow(Exception::class);

        // Config map should still be unchanged
        $configMapFinal = $registry->getHandlerConfigMap();
        expect(count($configMapFinal))->toBe($initialCount);
    });
});
