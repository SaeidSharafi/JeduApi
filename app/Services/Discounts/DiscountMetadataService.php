<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Traits\AdvanceEnum;
use Exception;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use Spatie\LaravelData\Data;
use Throwable;

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
     *   1. Manual override — `labels()`/`descriptions()`/`fieldMeta()` on the
     *      config class, only if you deliberately define them for a case i18n
     *      can't express.
     *   2. Automatic translation — `discount.handlers.{$key}.fields.{$param}.*`
     *   3. Generated fallback — humanized param name (keeps things usable
     *      before a translation is added).
     *
     * Array fields are enriched with `item_type` and friends so the frontend
     * can render model pickers, enum multi-selects, and nested Data repeaters
     * without out-of-band knowledge of each handler.
     *
     * @param  list<class-string>  $visited  recursion guard for nested Data
     */
    public function extractConfigSchema(string $configClass, string $key, array $visited = []): array
    {
        if (! class_exists($configClass) || in_array($configClass, $visited, true)) {
            return [];
        }

        $reflection  = new ReflectionClass($configClass);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return [];
        }

        $customDescriptions = method_exists($configClass, 'descriptions') ? $configClass::descriptions() : [];
        $customLabels       = method_exists($configClass, 'labels') ? $configClass::labels() : [];
        $fieldMeta          = method_exists($configClass, 'fieldMeta') ? $configClass::fieldMeta() : [];
        $rules              = $this->resolveRules($configClass);

        $schema = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $type      = $parameter->getType();
            $baseType  = $this->getParameterType($type);

            $label       = $customLabels[$paramName]       ?? $this->resolveFieldLabel($key, $paramName);
            $description = $customDescriptions[$paramName] ?? $this->resolveFieldDescription($key, $paramName, $type);

            $schema[$paramName] = [
                'key'         => $paramName,
                'type'        => $baseType,
                'label'       => $label,
                'required'    => ! $parameter->isOptional(),
                'description' => $description,
            ];

            if ($baseType === 'enum') {
                $this->enrichWithEnumCases($schema[$paramName], (string) $type);
            }

            if ($parameter->isDefaultValueAvailable()) {
                $schema[$paramName]['default'] = $parameter->getDefaultValue();
            }

            $this->enrichWithArrayItemMetadata(
                $schema[$paramName],
                $baseType,
                $paramName,
                $rules,
                $fieldMeta,
                $key,
                $visited,
                $configClass,
            );

            $this->enrichWithModelReference(
                $schema[$paramName],
                $baseType,
                $paramName,
                $rules,
                $fieldMeta,
            );
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

    /**
     * Resolve the rules array for a config class without triggering Laravel
     * Data's validation context (which requires a request). Falls back to an
     * empty array when `rules()` is context-dependent.
     *
     * @return array<string, mixed>
     */
    private function resolveRules(string $configClass): array
    {
        if (! method_exists($configClass, 'rules')) {
            return [];
        }

        try {
            /** @var array<string, mixed> $rules */
            $rules = $configClass::rules();

            return is_array($rules) ? $rules : [];
        } catch (Throwable) {
            // Some Data classes build rules from a ValidationContext. Those
            // are out of scope for metadata extraction — we simply skip
            // rules-driven detection for them and fall back to fieldMeta().
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $fieldEntry
     * @param  array<string, mixed>  $rules
     * @param  array<string, array<string, mixed>>  $fieldMeta
     */
    private function enrichWithArrayItemMetadata(
        array &$fieldEntry,
        string $baseType,
        string $paramName,
        array $rules,
        array $fieldMeta,
        string $key,
        array $visited,
        string $parentClass,
    ): void {
        if ($baseType !== 'array') {
            return;
        }

        $itemMetaOverride        = $fieldMeta[$paramName]['item'] ?? null;
        $itemType                = $itemMetaOverride['item_type'] ?? $this->detectArrayTypeItemType($paramName, $rules);
        $fieldEntry['item_type'] = $itemType;

        if ($itemType === 'enum') {
            $enumClass = $itemMetaOverride['item_enum']
                ?? $this->detectArrayEnumClass($paramName, $rules)
                ?? null;

            if ($enumClass && enum_exists($enumClass)) {
                $fieldEntry['item_enum'] = $enumClass;
                $this->enrichWithEnumCases($fieldEntry, $enumClass, 'item_cases');
            }
        }

        if ($itemType === 'model') {
            $reference = $itemMetaOverride['model_reference']
                ?? $this->detectArrayModelReference($paramName, $rules);

            if ($reference) {
                $fieldEntry['model_reference'] = $reference;
            }
        }

        if ($itemType === 'data') {
            $itemClass = $itemMetaOverride['item_class'] ?? null;

            if ($itemClass && is_a($itemClass, Data::class, true)) {
                $fieldEntry['item_class']  = $itemClass;
                $fieldEntry['item_schema'] = $this->extractConfigSchema(
                    $itemClass,
                    $key,
                    [...$visited, $parentClass],
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fieldEntry
     * @param  array<string, mixed>  $rules
     * @param  array<string, array<string, mixed>>  $fieldMeta
     */
    private function enrichWithModelReference(
        array &$fieldEntry,
        string $baseType,
        string $paramName,
        array $rules,
        array $fieldMeta,
    ): void {
        if (in_array($baseType, ['array', 'enum'], true)) {
            return;
        }

        $override = $fieldMeta[$paramName]['model_reference'] ?? null;
        if ($override) {
            $fieldEntry['model_reference'] = $override;

            return;
        }

        $reference = $this->detectScalarModelReference($paramName, $rules);
        if ($reference) {
            $fieldEntry['model_reference'] = $reference;
        }
    }

    private function detectArrayTypeItemType(string $paramName, array $rules): string
    {
        if ($this->detectArrayEnumClass($paramName, $rules)) {
            return 'enum';
        }

        if ($this->detectArrayModelReference($paramName, $rules)) {
            return 'model';
        }

        $scalar = $this->detectArrayScalarType($paramName, $rules);
        if ($scalar) {
            return $scalar;
        }

        return 'mixed';
    }

    private function detectArrayEnumClass(string $paramName, array $rules): ?string
    {
        $starKey    = "{$paramName}.*";
        $candidates = $rules[$starKey] ?? null;
        if ($candidates === null) {
            return null;
        }

        $candidates = is_array($candidates) ? $candidates : [$candidates];

        foreach ($candidates as $rule) {
            if ($rule instanceof \Illuminate\Validation\Rules\Enum) {
                $reflection = new ReflectionClass($rule);
                if ($reflection->hasProperty('type')) {
                    $prop = $reflection->getProperty('type');
                    $prop->setAccessible(true);

                    /** @var string $enumClass */
                    $enumClass = $prop->getValue($rule);
                    if (is_string($enumClass) && class_exists($enumClass)) {
                        return $enumClass;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function detectArrayModelReference(string $paramName, array $rules): ?array
    {
        $starKey    = "{$paramName}.*";
        $candidates = $rules[$starKey] ?? null;
        if ($candidates === null) {
            return null;
        }

        $candidates = is_array($candidates) ? $candidates : [$candidates];

        foreach ($candidates as $rule) {
            if (is_string($rule)) {
                $parsed = $this->parseExistsRule($rule);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>|null
     */
    private function detectScalarModelReference(string $paramName, array $rules): ?array
    {
        $candidates = $rules[$paramName] ?? null;
        if ($candidates === null) {
            return null;
        }

        $candidates = is_array($candidates) ? $candidates : [$candidates];

        foreach ($candidates as $rule) {
            if (is_string($rule)) {
                $parsed = $this->parseExistsRule($rule);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    private function detectArrayScalarType(string $paramName, array $rules): ?string
    {
        $starKey    = "{$paramName}.*";
        $candidates = $rules[$starKey] ?? null;
        if ($candidates === null) {
            return null;
        }

        $candidates = is_array($candidates) ? $candidates : [$candidates];

        foreach ($candidates as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $rule   = mb_trim($rule);
            $result = match (true) {
                str_starts_with($rule, 'integer'),
                str_starts_with($rule, 'int'),
                str_starts_with($rule, 'numeric'),
                str_starts_with($rule, 'number') => 'integer',
                str_starts_with($rule, 'string') => 'string',
                str_starts_with($rule, 'boolean'),
                str_starts_with($rule, 'bool') => 'boolean',
                default                        => null,
            };

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Parse a string validation rule like `exists:products,id` into a
     * model_reference array. Returns null when the rule isn't an `exists:`
     * rule (or lacks a column).
     *
     * @return array<string, string>|null
     */
    private function parseExistsRule(string $rule): ?array
    {
        if (! str_starts_with($rule, 'exists:')) {
            return null;
        }

        $parts  = explode(',', mb_substr($rule, mb_strlen('exists:')));
        $table  = mb_trim($parts[0] ?? '');
        $column = mb_trim($parts[1] ?? 'id');

        if ($table === '') {
            return null;
        }

        return [
            'table'  => $table,
            'column' => $column !== '' ? $column : 'id',
        ];
    }

    /**
     * Populate `cases` (or a custom key) from an enum, honoring AdvanceEnum.
     *
     * @param  array<string, mixed>  $fieldEntry
     */
    private function enrichWithEnumCases(array &$fieldEntry, string $enumClass, string $targetKey = 'cases'): void
    {
        if (! enum_exists($enumClass)) {
            return;
        }

        $reflection = new ReflectionClass($enumClass);
        $traits     = $reflection->getTraitNames() ?: [];

        if (in_array(AdvanceEnum::class, $traits, true)) {
            $fieldEntry[$targetKey] = $enumClass::getValueLabel();

            return;
        }

        $cases = [];
        foreach ($enumClass::cases() as $case) {
            $value   = property_exists($case, 'value') ? $case->value : $case->name;
            $cases[] = ['value' => $value, 'label' => Str::title((string) $value)];
        }
        $fieldEntry[$targetKey] = $cases;
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
