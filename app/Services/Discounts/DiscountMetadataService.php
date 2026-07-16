<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Traits\AdvanceEnum;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use Exception;

final class DiscountMetadataService
{
    public function __construct(
        private readonly DiscountHandlerRegistry $handlerRegistry
    ) {}

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

    public function getConditions(): array
    {
        $conditions       = ['cart' => [], 'product' => []];
        $handlerConfigMap = $this->handlerRegistry->getHandlerConfigMap();

        foreach ($this->handlerRegistry->getCartConditionHandlers() as $key => $handlerClass) {
            $conditions['cart'][] = $this->buildEntry($key, $handlerClass, $handlerConfigMap);
        }
        foreach ($this->handlerRegistry->getProductConditionHandlers() as $key => $handlerClass) {
            $conditions['product'][] = $this->buildEntry($key, $handlerClass, $handlerConfigMap);
        }
        return $conditions;
    }

    public function getActions(): array
    {
        $actions          = ['cart' => [], 'product' => []];
        $handlerConfigMap = $this->handlerRegistry->getHandlerConfigMap();

        foreach ($this->handlerRegistry->getCartActionHandlers() as $key => $handlerClass) {
            $actions['cart'][] = $this->buildEntry($key, $handlerClass, $handlerConfigMap);
        }
        foreach ($this->handlerRegistry->getProductActionHandlers() as $key => $handlerClass) {
            $actions['product'][] = $this->buildEntry($key, $handlerClass, $handlerConfigMap);
        }

        return $actions;
    }

    /**
     * Builds a single condition/action metadata entry. `handler_class` is
     * included for both conditions and actions (previously only conditions
     * had it — worth confirming that asymmetry wasn't intentional on the
     * frontend before shipping this).
     */
    private function buildEntry(string $key, string $handlerClass, array $handlerConfigMap): array
    {
        $configClass = $handlerConfigMap[$handlerClass] ?? null;

        return [
            'key'                  => $key,
            'name'                 => $this->resolveHandlerName($key),
            'description'          => $this->resolveHandlerDescription($key, $handlerClass),
            'handler_class'        => $handlerClass,
            'configuration_schema' => $configClass ? $this->extractConfigSchema($configClass, $key) : [],
        ];
    }

    public function getOperators(): array
    {
        return collect([
            'greater_than_or_equal' => '>=',
            'greater_than'          => '>',
            'less_than_or_equal'    => '<=',
            'less_than'             => '<',
            'equal'                 => '=',
        ])->map(fn (string $symbol, string $value) => [
            'value'  => $value,
            'label'  => __("discount.operators.{$value}"),
            'symbol' => $symbol,
        ])->values()->all();
    }

    public function getTypes(): array
    {
        return collect(['product_specific', 'cart_checkout'])
            ->map(fn (string $value) => [
                'value'       => $value,
                'label'       => __("discount.types.{$value}.label"),
                'description' => __("discount.types.{$value}.description"),
            ])->all();
    }

    /**
     * Extracts the configuration schema for a handler's config Data class.
     *
     * Resolution order per field is:
     *   1. Manual override — `labels()`/`descriptions()` on the config class,
     *      only if you deliberately define them for a case i18n can't express.
     *   2. Automatic translation — `discount.handlers.{$key}.fields.{$param}.*`
     *   3. Generated fallback — humanized param name (keeps things usable
     *      before a translation is added).
     */
    public function extractConfigSchema(string $configClass, string $key): array
    {
        if (! class_exists($configClass)) {
            return [];
        }

        $reflection  = new ReflectionClass($configClass);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return [];
        }

        $customDescriptions = method_exists($configClass, 'descriptions') ? $configClass::descriptions() : [];
        $customLabels       = method_exists($configClass, 'labels') ? $configClass::labels() : [];

        $schema = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $type      = $parameter->getType();

            $label       = $customLabels[$paramName]       ?? $this->resolveFieldLabel($key, $paramName);
            $description = $customDescriptions[$paramName] ?? $this->resolveFieldDescription($key, $paramName, $type);

            $schema[$paramName] = [
                'key'         => $paramName,
                'type'        => $this->getParameterType($type),
                'label'       => $label,
                'required'    => ! $parameter->isOptional(),
                'description' => $description,
            ];

            if ($this->getParameterType($type) === 'enum') {
                $paramClassName       = (string) $type;
                $paramClassReflection = new ReflectionClass($paramClassName);
                $cases                = [];

                if (in_array(AdvanceEnum::class, $paramClassReflection->getTraitNames())) {
                    $schema[$paramName]['cases'] = $paramClassName::getValueLabel();
                } else {
                    foreach ($paramClassName::cases() as $case) {
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
        \App\Enums\Order\DiscountTypeEnum $discountType
    ): ?string {
        $handlerClass = $this->handlerRegistry->getHandlerClassByKey($handlerKey, $handlerType, $discountType);
        if (! $handlerClass) {
            return null;
        }

        if (method_exists($handlerClass, 'getConfigClass')) {
            try {
                return $handlerClass::getConfigClass();
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    public function getParameterType(?ReflectionType $type): string
    {
        if (! $type) {
            return 'mixed';
        }
        if (! $type instanceof ReflectionNamedType) {
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

    private function resolveHandlerName(string $key): string
    {
        $translationKey = "discount.handlers.{$key}.name";

        return Lang::has($translationKey) ? __($translationKey) : $this->humanize($key);
    }

    private function resolveHandlerDescription(string $key, string $handlerClass): string
    {
        $translationKey = "discount.handlers.{$key}.description";

        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return $this->humanizeClassName($handlerClass);
    }

    private function resolveFieldLabel(string $key, string $paramName): string
    {
        $translationKey = "discount.handlers.{$key}.fields.{$paramName}.label";

        return Lang::has($translationKey) ? __($translationKey) : $this->humanize($paramName);
    }

    private function resolveFieldDescription(string $key, string $paramName, ?ReflectionType $type): string
    {

        $translationKey = "discount.handlers.{$key}.fields.{$paramName}.description";
        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return sprintf('%s (%s)', $this->humanize($paramName), $this->getParameterType($type));
    }

    private function humanize(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->title()->toString();
    }

    private function humanizeClassName(string $handlerClass): string
    {
        $className = class_basename($handlerClass);
        $className = preg_replace('/(Condition|Action)$/', '', $className);
        $spaced    = preg_replace('/([A-Z])/', ' $1', $className);

        return mb_trim($spaced);
    }
}
