<?php

namespace App\Scribe\Extracting\Strategies\BodyParameters;

use App\Scribe\Extracting\Strategies\GetFromLaravelDataBase;
use ReflectionClass;

class GetFromLaravelData extends GetFromLaravelDataBase
{
    protected string $customParameterDataMethodName = 'bodyParameters';

    protected function isLaravelDataMeantForThisStrategy(ReflectionClass $laravelDataReflectionClass): bool
    {
        // Only use this FormRequest for body params if there's no "Query parameters" in the docblock
        // Or there's a bodyParameters() method
        $formRequestDocBlock = $laravelDataReflectionClass->getDocComment();
        if (str_contains(strtolower($formRequestDocBlock), "query parameters")
            || $laravelDataReflectionClass->hasMethod('queryParameters')) {
            return false;
        }

        return true;
    }
}
