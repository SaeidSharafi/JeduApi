<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use ReflectionClass;

final class DiscountMetadataService
{
    public function __construct(
        private readonly DiscountHandlerRegistry $handlerRegistry
    ) {}

    /**
     * Get available discount conditions with their metadata.
     */
    public function getConditions(): array
    {
        $conditions = [];
        $conditionHandlers = $this->handlerRegistry->getCartConditionHandlers();
        $handlerConfigMap = $this->handlerRegistry->getHandlerConfigMap();

        foreach ($conditionHandlers as $key => $handlerClass) {
            $configClass = $handlerConfigMap[$handlerClass] ?? null;

            $conditions[] = [
                'key' => $key,
                'name' => $this->generateNameFromKey($key),
                'description' => $this->generateDescriptionFromClass($handlerClass),
                'handler_class' => $handlerClass,
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
        $actions = [];
        $actionHandlers = $this->handlerRegistry->getCartActionHandlers();
        $handlerConfigMap = $this->handlerRegistry->getHandlerConfigMap();

        foreach ($actionHandlers as $key => $handlerClass) {
            $configClass = $handlerConfigMap[$handlerClass] ?? null;

            $actions[] = [
                'key' => $key,
                'name' => $this->generateNameFromKey($key),
                'description' => $this->generateDescriptionFromClass($handlerClass),
                'handler_class' => $handlerClass,
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
                'value' => 'greater_than_or_equal',
                'label' => 'Greater than or equal (>=)',
                'symbol' => '>=',
            ],
            [
                'value' => 'greater_than',
                'label' => 'Greater than (>)',
                'symbol' => '>',
            ],
            [
                'value' => 'less_than_or_equal',
                'label' => 'Less than or equal (<=)',
                'symbol' => '<=',
            ],
            [
                'value' => 'less_than',
                'label' => 'Less than (<)',
                'symbol' => '<',
            ],
            [
                'value' => 'equal',
                'label' => 'Equal (=)',
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
                'value' => 'product_specific',
                'label' => 'Product Specific',
                'description' => 'Discount applied to specific products',
            ],
            [
                'value' => 'cart_checkout',
                'label' => 'Cart Checkout',
                'description' => 'Discount applied to entire cart during checkout',
            ],
        ];
    }

    /**
     * Get validation rules for creating discount promotions.
     */
    public function getValidationRules(): array
    {
        return [
            'name' => [
                'required' => true,
                'type' => 'string',
                'max_length' => 255,
                'description' => 'Promotion name',
            ],
            'description' => [
                'required' => true,
                'type' => 'string',
                'max_length' => 1000,
                'description' => 'Promotion description',
            ],
            'type' => [
                'required' => true,
                'type' => 'string',
                'options' => ['product_specific', 'cart_checkout'],
                'description' => 'Promotion type',
            ],
            'is_active' => [
                'required' => false,
                'type' => 'boolean',
                'default' => true,
                'description' => 'Whether the promotion is active',
            ],
            'starts_at' => [
                'required' => false,
                'type' => 'datetime',
                'format' => 'Y-m-d H:i:s',
                'description' => 'When the promotion starts (optional)',
            ],
            'ends_at' => [
                'required' => false,
                'type' => 'datetime',
                'format' => 'Y-m-d H:i:s',
                'description' => 'When the promotion ends (optional)',
            ],
            'priority' => [
                'required' => false,
                'type' => 'integer',
                'min' => 0,
                'max' => 1000,
                'default' => 0,
                'description' => 'Promotion priority (higher = evaluated first)',
            ],
            'stop_processing_subsequent_rules' => [
                'required' => false,
                'type' => 'boolean',
                'default' => false,
                'description' => 'Stop processing other promotions if this one applies',
            ],
            'usage_limit_total' => [
                'required' => false,
                'type' => 'integer',
                'min' => 1,
                'description' => 'Total usage limit across all customers',
            ],
            'usage_limit_per_customer' => [
                'required' => false,
                'type' => 'integer',
                'min' => 1,
                'description' => 'Usage limit per customer',
            ],
            'rules' => [
                'required' => true,
                'type' => 'array',
                'description' => 'Array of discount rules (conditions and actions)',
            ],
            'coupons' => [
                'required' => false,
                'type' => 'array',
                'description' => 'Array of coupon codes for this promotion',
            ],
        ];
    }

    /**
     * Extract configuration schema from a config class using reflection.
     */
    public function extractConfigSchema(string $configClass): array
    {
        if (!class_exists($configClass)) {
            return [];
        }

        $reflection = new ReflectionClass($configClass);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return [];
        }

        $schema = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $type = $parameter->getType();

            $schema[$paramName] = [
                'type' => $this->getParameterType($type),
                'required' => !$parameter->isOptional(),
                'description' => $this->generateParameterDescription($paramName, $type),
            ];

            if ($parameter->isDefaultValueAvailable()) {
                $schema[$paramName]['default'] = $parameter->getDefaultValue();
            }
        }

        return $schema;
    }

    /**
     * Get parameter type as string.
     */
    public function getParameterType($type): string
    {
        if (!$type) {
            return 'mixed';
        }

        $typeName = $type->getName();

        return match ($typeName) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'string' => 'string',
            'array' => 'array',
            default => $typeName,
        };
    }

    /**
     * Generate parameter description from name and type.
     */
    public function generateParameterDescription(string $paramName, $type): string
    {
        $humanName = ucwords(str_replace('_', ' ', $paramName));
        $typeName = $this->getParameterType($type);

        return "{$humanName} ({$typeName})";
    }

    /**
     * Generate a human-readable name from a handler key.
     */
    public function generateNameFromKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Generate a description from a handler class name.
     */
    public function generateDescriptionFromClass(string $handlerClass): string
    {
        $className = class_basename($handlerClass);

        // Remove common suffixes
        $className = preg_replace('/(Condition|Action)$/', '', $className);

        // Convert CamelCase to space separated
        $description = preg_replace('/([A-Z])/', ' $1', $className);
        $description = trim($description);

        return $description;
    }

}
