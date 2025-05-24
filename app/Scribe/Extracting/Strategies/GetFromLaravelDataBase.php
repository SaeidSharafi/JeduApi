<?php

declare(strict_types=1);

namespace App\Scribe\Extracting\Strategies;

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
        if (method_exists($data, 'getValidationRules')) {
            $properties = get_object_vars($data);

            return app()->call([$data, 'getValidationRules'], ['payload' => $properties]);
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

        $parametersFromLaravelData = $this->getParametersFromValidationRules(
            $this->getRouteValidationRules($laravelData),
            $this->getCustomParameterData($laravelData)
        );

        return $this->normaliseArrayAndObjectParameters($parametersFromLaravelData);
    }
}
