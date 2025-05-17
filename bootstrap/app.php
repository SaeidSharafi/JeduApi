<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/Api/V1/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {})
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->validationErrors($e->errors(), $e->getMessage());
            }

            return null;
        });
        $exceptions->renderable(function (Exception $e, $request) {
            if ($request->expectsJson()) {
                return response()->serverError($e->getMessage(), $e);

            }

            return null;
        });
    })->create();
