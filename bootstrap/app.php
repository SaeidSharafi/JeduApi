<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            '/webhooks/github-deployer',
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->is('admin/*') || $request->expectsJson()) {
                return null;
            }

            return null;
        });

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = function (Request $request) {
            return $request->expectsJson()
                || $request->is('api/*')
                || str_starts_with($request->path(), 'api/');
        };

        // 1. ValidationException (422 Unprocessable Entity)
        $exceptions->renderable(function (ValidationException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                // Use your specific macro for validation errors
                return response()->validationErrors($e->errors(), $e->getMessage());
            }

            return null;
        });

        // 2. AuthenticationException (401 Unauthorized)
        $exceptions->renderable(function (AuthenticationException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                // Use your 'unauthorized' macro
                return response()->unauthorized($e->getMessage());
            }

            return null;
        });

        // 3. AccessDeniedHttpException (403 Forbidden)
        // (e.g., from Gate denials or Policies if user is authenticated but not authorized)
        $exceptions->renderable(function (AccessDeniedHttpException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                return response()->forbidden($e->getMessage());
            }

            return null;
        });

        // 4. ModelNotFoundException (Typically leads to 404)
        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                // Extract model name for a slightly more specific message if desired
                $modelName = class_basename($e->getModel());

                return response()->notFound("Resource '{$modelName}' not found.");
            }

            return null;
        });

        // 5. NotFoundHttpException (404 Not Found - for routes or other non-model 404s)
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                return response()->notFound($e->getMessage() ?: 'The requested resource was not found.');
            }

            return null;
        });

        // 6. MethodNotAllowedHttpException (405 Method Not Allowed)
        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                return response()->methodNotAllowed($e->getMessage() ?: 'Method not allowed for this resource.');
            }

            return null;
        });

        // 7. Other generic HttpExceptions (e.g., 419 CSRF, 400 Bad Request not caught by others)
        $exceptions->renderable(function (
            Symfony\Component\HttpKernel\Exception\HttpException $e,
            Request $request
        ) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                return response()->error($e->getMessage(), $e->getStatusCode());
            }

            return null;
        });

        // 8. Generic Fallback for any other Throwable (defaults to 500 Internal Server Error)
        $exceptions->renderable(function (Throwable $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                if (app()->isProduction()) {
                    Log::error($e->getMessage(), ['exception' => $e]);
                }

                $message = config('app.debug') ? $e->getMessage() : 'An internal server error occurred.';
                $exceptionForMacro = config('app.debug') ? $e : null;

                return response()->serverError($message, $exceptionForMacro);
            }

            return null;
        });
    })->create();
