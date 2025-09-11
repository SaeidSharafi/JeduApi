<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Enums\Order\DiscountTypeEnum;
use App\Traits\AdvanceEnum;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use Throwable;

final class DiscountMetadataService
{
    public function __construct(
        private readonly DiscountHandlerRegistry $handlerRegistry
    ) {}

    /**
     * Get metadata for all available discount promotions.
     */
    public function getMetadata(): array
    {
        $conditions = $this->getConditions();
        $actions    = $this->getActions();

        return [
            'cart' => [
                'conditions' => $conditions['cart'],
                'actions'    => $actions['cart'],
            ],
            'product' => [
                'conditions' => $conditions['product'],
                'actions'    => $actions['product'],
            ],
        ];
    }

    /**
     * Get available discount conditions with their metadata.
     */
    public function getConditions(): array
    {
        $conditions               = [];
        $cartConditionHandlers    = $this->handlerRegistry->getCartConditionHandlers();
        $productConditionHandlers = $this->handlerRegistry->getProductConditionHandlers();
        $handlerConfigMap         = $this->handlerRegistry->getHandlerConfigMap();

        foreach ($cartConditionHandlers as $key => $handlerClass) {
            $configClass = $handlerConfigMap[$handlerClass] ?? null;

            $conditions['cart'][] = [
                'key'                  => $key,
                'name'                 => $this->generateNameFromKey($key),
                'description'          => $this->generateDescription($handlerClass, $key),
                'handler_class'        => $handlerClass,
                'configuration_schema' => $configClass ? $this->extractConfigSchema($configClass) : [],
            ];
        }
        foreach ($productConditionHandlers as $key => $handlerClass) {
            $configClass = $handlerConfigMap[$handlerClass] ?? null;

            $conditions['product'][] = [
                'key'                  => $key,
                'name'                 => $this->generateNameFromKey($key),
                'description'          => $this->generateDescription($handlerClass, $key),
                'handler_class'        => $handlerClass,
                'configuration_schema' => $configClass ? $this->extractConfigSchema($configClass) : [],
            ];
        }

        return $conditions;
    }

    /**
     * Get available discount actions with their metadata.
     */
    public function getActions(): array
    {
        $actions               = [];
        $actionHandlers        = $this->handlerRegistry->getCartActionHandlers();
        $productActionHandlers = $this->handlerRegistry->getProductActionHandlers();
        $handlerConfigMap      = $this->handlerRegistry->getHandlerConfigMap();

        foreach ($actionHandlers as $key => $handlerClass) {
            $configClass = $handlerConfigMap[$handlerClass] ?? null;

            $actions['cart'][] = [
                'key'                  => $key,
                'name'                 => $this->generateNameFromKey($key),
                'description'          => $this->generateDescription($handlerClass, $key),
                'configuration_schema' => $configClass ? $this->extractConfigSchema($configClass) : [],
            ];
        }
        foreach ($productActionHandlers as $key => $handlerClass) {
            $configClass = $handlerConfigMap[$handlerClass] ?? null;

            $actions['product'][] = [
                'key'                  => $key,
                'name'                 => $this->generateNameFromKey($key),
                'description'          => $this->generateDescription($handlerClass, $key),
                'configuration_schema' => $configClass ? $this->extractConfigSchema($configClass) : [],
            ];
        }

        return $actions;
    }

    /**
     * Get all available discount operators.
     */
    public function getOperators(): array
    {
        return [
            [
                'value'  => 'greater_than_or_equal',
                'label'  => 'Greater than or equal (>=)',
                'symbol' => '>=',
            ],
            [
                'value'  => 'greater_than',
                'label'  => 'Greater than (>)',
                'symbol' => '>',
            ],
            [
                'value'  => 'less_than_or_equal',
                'label'  => 'Less than or equal (<=)',
                'symbol' => '<=',
            ],
            [
                'value'  => 'less_than',
                'label'  => 'Less than (<)',
                'symbol' => '<',
            ],
            [
                'value'  => 'equal',
                'label'  => 'Equal (=)',
                'symbol' => '=',
            ],
        ];
    }

    /**
     * Get discount promotion types.
     */
    public function getTypes(): array
    {
        return [
            [
                'value'       => 'product_specific',
                'label'       => 'Product Specific',
                'description' => 'Discount applied to specific products',
            ],
            [
                'value'       => 'cart_checkout',
                'label'       => 'Cart Checkout',
                'description' => 'Discount applied to entire cart during checkout',
            ],
        ];
    }

    /**
     * Extract configuration schema from a config class using reflection.
     */
    public function extractConfigSchema(string $configClass): array
    {
        if (! class_exists($configClass)) {
            return [];
        }

        $reflection  = new ReflectionClass($configClass);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return [];
        }

        $customDescriptions = [];
        if (method_exists($configClass, 'descriptions')) {
            $customDescriptions = $configClass::descriptions();
        }

        $schema = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName          = $parameter->getName();
            $type               = $parameter->getType();
            $description        = $customDescriptions[$paramName] ?? $this->generateParameterDescription($paramName, $type);
            $schema[$paramName] = [
                'type'        => $this->getParameterType($type),
                'required'    => ! $parameter->isOptional(),
                'description' => $description,
            ];
            if ($this->getParameterType($type) === 'enum') {
                $paramClassName = (string) $type;

                $paramClassReflection = new ReflectionClass($paramClassName);
                $cases                = [];

                if (in_array(AdvanceEnum::class, $paramClassReflection->getTraitNames())) {
                    $schema[$paramName]['cases'] = $paramClassName::getValueLabel();
                } else {
                    foreach ($paramClassName::cases() as $case) {
                        // For backed enums (e.g., enum Color: string), use the value. Otherwise, use the name.
                        $value   = property_exists($case, 'value') ? $case->value : $case->name;
                        $cases[] = ['value' => $value, 'label' => Str::title($value)];
                    }
                    $schema[$paramName]['cases'] = $cases;
                }

            }
            if ($parameter->isDefaultValueAvailable()) {
                $schema[$paramName]['default'] = $parameter->getDefaultValue();
            }
        }

        return $schema;
    }

    public function getConfigurationClass(
        string $handlerKey,
        string $handlerType,
        DiscountTypeEnum $discountType
    ): ?string {
        $handlerClass = $this->handlerRegistry->getHandlerClassByKey($handlerKey, $handlerType, $discountType);
        if (! $handlerClass) {
            return null;
        }

        // Check if the handler class has a static method to get the config class
        if (method_exists($handlerClass, 'getConfigClass')) {
            try {
                return $handlerClass::getConfigClass();
            } catch (Throwable $e) {
                // If the method fails, we return null
                return null;
            }
        }

        // If the handler class does not have a method to get the config class, we return null
        return null;
    }

    /**
     * Get parameter type as string.
     */
    public function getParameterType(?ReflectionType $type): string
    {
        if (! $type) {
            return 'mixed';
        }
        if (! $type instanceof ReflectionNamedType) {
            // Fallback for complex types like UnionType, etc.
            return (string) $type;
        }

        $typeName = $type->getName();

        if (! $type->isBuiltin() && enum_exists($typeName)) {
            return 'enum';
        }

        return match ($typeName) {
            'int'    => 'integer',
            'float'  => 'number',
            'bool'   => 'boolean',
            'string' => 'string',
            'array'  => 'array',
            default  => $typeName,
        };
    }

    /**
     * Generate parameter description from name and type.
     */
    public function generateParameterDescription(string $paramName, $type): string
    {
        $humanName = ucwords(str_replace('_', ' ', $paramName));
        $typeName  = $this->getParameterType($type);

        return "{$humanName} ({$typeName})";
    }

    /**
     * Generate a human-readable name from a handler key.
     */
    public function generateNameFromKey(string $key): string
    {
        if (Lang::has("discount.name.{$key}")) {
            return __("discount.name.{$key}");
        }

        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Generate a description from a handler class name.
     */
    public function generateDescription(string $handlerClass, string $key): string
    {
        if (Lang::has("discount.description.{$key}")) {
            return __("discount.description.{$key}");
        }
        $className = class_basename($handlerClass);

        // Remove common suffixes
        $className = preg_replace('/(Condition|Action)$/', '', $className);

        // Convert CamelCase to space separated
        $description = preg_replace('/([A-Z])/', ' $1', $className);

        return mb_trim($description);
    }
}
