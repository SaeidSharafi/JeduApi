<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Controllers\Api\Admin\DiscountInfoController;
use App\Services\Discounts\OrderCalculationService;
use ReflectionClass;
use ReflectionType;

final class DiscountInfoControllerTestHelper
{
    private DiscountInfoController $controller;

    private ReflectionClass $reflection;

    public function __construct()
    {
        $this->controller = new DiscountInfoController(
            app(OrderCalculationService::class)
        );
        $this->reflection = new ReflectionClass($this->controller);
    }

    /**
     * Create a test class dynamically for testing purposes.
     */
    public static function createTestClass(string $className, string $constructor = ''): void
    {
        if (class_exists($className)) {
            return;
        }

        $classDefinition = "class {$className} { {$constructor} }";
        eval($classDefinition);
    }

    /**
     * Create a mock type object for testing parameter types.
     */
    public static function createMockType(string $typeName): object
    {
        return new class($typeName)
        {
            public function __construct(private string $typeName) {}

            public function getName(): string
            {
                return $this->typeName;
            }
        };
    }

    /**
     * Get reflection parameter type from a test class.
     */
    public static function getParameterTypeFromTestClass(string $className, int $parameterIndex = 0): ?ReflectionType
    {
        $reflectionClass = new ReflectionClass($className);
        $constructor     = $reflectionClass->getConstructor();

        if (! $constructor) {
            return null;
        }

        $parameters = $constructor->getParameters();

        if (! isset($parameters[$parameterIndex])) {
            return null;
        }

        return $parameters[$parameterIndex]->getType();
    }

    public function extractConfigSchema(string $configClass): array
    {
        $method = $this->reflection->getMethod('extractConfigSchema');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $configClass);
    }

    public function getParameterType($type): string
    {
        $method = $this->reflection->getMethod('getParameterType');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $type);
    }

    public function generateParameterDescription(string $paramName, $type): string
    {
        $method = $this->reflection->getMethod('generateParameterDescription');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $paramName, $type);
    }

    public function generateDescriptionFromClass(string $handlerClass): string
    {
        $method = $this->reflection->getMethod('generateDescriptionFromClass');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $handlerClass);
    }

    public function generateNameFromKey(string $key): string
    {
        $method = $this->reflection->getMethod('generateNameFromKey');
        $method->setAccessible(true);

        return $method->invoke($this->controller, $key);
    }
}
