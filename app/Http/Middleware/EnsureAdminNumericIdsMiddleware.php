<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminNumericIdsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        // Only run on admin routes
        if ($route && $request->is('api/v1/admin/*')) {

            // Find all route parameters type-hinted with an Eloquent Model
            foreach ($route->signatureParameters(Model::class) as $parameter) {
                $name  = $parameter->getName();
                $value = $route->parameter($name);

                // If the parameter value is non-numeric
                if ($value !== null && ! is_numeric($value)) {
                    $modelClass = $parameter->getType() ? $parameter->getType()->getName() : null;

                    if ($modelClass && is_subclass_of($modelClass, Model::class)) {
                        $model = new $modelClass;

                        // Check if the route is binding to the primary key of an integer-based model
                        $bindingFields = $route->bindingFields();
                        $bindingField  = $bindingFields[$name] ?? $model->getKeyName();

                        if ($bindingField === $model->getKeyName() && $model->getKeyType() === 'int') {
                            abort(404); // Instantly throw 404 and skip the database query
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
