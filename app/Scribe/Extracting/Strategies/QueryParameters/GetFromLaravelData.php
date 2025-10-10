<?php

declare(strict_types=1);

namespace App\Scribe\Extracting\Strategies\QueryParameters;

use App\Scribe\Extracting\Strategies\GetFromLaravelDataBase;
use ReflectionClass;

final class GetFromLaravelData extends GetFromLaravelDataBase
{
    protected string $customParameterDataMethodName = 'queryParameters';

    protected function isLaravelDataMeantForThisStrategy(ReflectionClass $laravelDataReflectionClass): bool
    {
        // Only use this for query params if there's a queryParameters() method
        // or "Query parameters" is mentioned in the docblock
        if ($laravelDataReflectionClass->hasMethod('queryParameters')) {
            return true;
        }

        $formRequestDocBlock = $laravelDataReflectionClass->getDocComment();
        if ($formRequestDocBlock && str_contains(mb_strtolower($formRequestDocBlock), 'query parameters')) {
            return true;
        }

        return false;
    }
}
