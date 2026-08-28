<?php

declare(strict_types=1);

namespace App\Scribe\Extracting\Strategies;

use Exception;
use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionUnionType;
use Spatie\LaravelData\Data;

abstract class GetFromLaravelDataBase extends Strategy
{
    use ParsesValidationRules;

    protected string $customParameterDataMethodName = '';

    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        return $this->getParametersFromLaravelData($endpointData->method, $endpointData->route);
    }

    protected function getRouteValidationRules(Data $data): mixed
    {
        try {
            if (method_exists($data, 'rules')) {
                return $data::rules();
            }
            if (method_exists($data, 'getValidationRules')) {
                $properties = get_object_vars($data);

                return app()->call([$data, 'getValidationRules'], ['payload' => $properties]);
            }
        } catch (Exception $e) {
            // During Scribe documentation generation, route parameters may not be available
            // causing rules() to fail. We'll fall back to using only the custom parameter data.
            c::warn('Failed to extract validation rules from '.get_class($data).': '.$e->getMessage());
            c::warn('Falling back to using only '.$this->customParameterDataMethodName.'() method for parameter documentation.');

            return [];
        }

        return [];
    }

    protected function getCustomParameterData(Data $data): mixed
    {
        if (method_exists($data, $this->customParameterDataMethodName)) {
            return call_user_func_array([$data, $this->customParameterDataMethodName], []);
        }

        c::warn("No {$this->customParameterDataMethodName}() method found in ".get_class($data).'. Scribe will only be able to extract basic information from the rules() method.');

        return [];
    }

    protected function getMissingCustomDataMessage(string $parameterName): string
    {
        return "No data found for parameter '$parameterName' in your {$this->customParameterDataMethodName}() method. Add an entry for '$parameterName' so you can add a description and example.";
    }

    protected function getLaravelDataReflectionClass(ReflectionFunctionAbstract $method): ?ReflectionClass
    {
        foreach ($method->getParameters() as $argument) {
            $argType = $argument->getType();
            if ($argType === null || $argType instanceof ReflectionUnionType) {
                continue;
            }

            $argumentClassName = $argType->getName();

            if (! class_exists($argumentClassName)) {
                continue;
            }

            try {
                $argumentClass = new ReflectionClass($argumentClassName);
            } catch (ReflectionException $e) {
                continue;
            }

            if (
                (class_exists(Data::class) && $argumentClass->isSubclassOf(Data::class))) {
                return $argumentClass;
            }
        }

        return null;
    }

    protected function hasDocBlock(ReflectionClass $laravelDataReflectionClass): bool
    {
        return $laravelDataReflectionClass->hasMethod($this->customParameterDataMethodName);
    }

    protected function hasBodyParameter(ReflectionClass $laravelDataReflectionClass): bool
    {
        return $laravelDataReflectionClass->hasMethod($this->customParameterDataMethodName);
    }

    private function getParametersFromLaravelData(ReflectionFunctionAbstract $method, Route $route): array
    {
        if (! $laravelDataReflectionClass = $this->getLaravelDataReflectionClass($method)) {
            return [];
        }

        if (! $this->isLaravelDataMeantForThisStrategy($laravelDataReflectionClass)) {
            return [];
        }

        $className = $laravelDataReflectionClass->getName();

        $laravelData = (new ReflectionClass($className))->newInstanceWithoutConstructor();

        // Skip if this Data class doesn't have the expected method for this strategy
        if (! method_exists($laravelData, $this->customParameterDataMethodName)) {
            return [];
        }

        $parametersFromLaravelData = $this->getParametersFromValidationRules(
            $this->getRouteValidationRules($laravelData),
            $this->getCustomParameterData($laravelData)
        );

        $ignoredProperties  = $this->getIgnoredProperties($method);
        $filteredParameters = array_filter(
            $parametersFromLaravelData,
            fn ($parameterName): bool => ! in_array($parameterName, $ignoredProperties, true),
            ARRAY_FILTER_USE_KEY
        );

        return $this->normaliseArrayAndObjectParameters($filteredParameters);
    }

    /**
     * Get the properties that should be ignored from the documentation based on the @ignoreQueryParam annotation.
     */
    private function getIgnoredProperties(ReflectionFunctionAbstract $method): array
    {
        $docComment = $method->getDocComment();
        if (! $docComment) {
            return [];
        }

        // This regex finds all "@ignoreQueryParam" tags and captures the text that follows on the same line.
        preg_match_all('/@ignoreQueryParam\s+(.*)/', $docComment, $matches);

        // If no matches were found, return an empty array.
        if (empty($matches[1])) {
            return [];
        }

        $ignoredProperties = [];
        // $matches[1] contains an array of all captured strings (the text after the tag).
        foreach ($matches[1] as $paramsString) {
            // Split the string by commas to handle multiple parameters on one line.
            $propertiesOnThisLine = explode(',', $paramsString);
            foreach ($propertiesOnThisLine as $property) {
                $trimmedProperty = mb_trim($property);
                if ($trimmedProperty) { // Ensure we don't add empty strings
                    $ignoredProperties[] = $trimmedProperty;
                }
            }
        }

        return $ignoredProperties;
    }
}
