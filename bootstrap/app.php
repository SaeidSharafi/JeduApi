<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
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
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('discounts:reindex-all')
            ->hourly()
            ->withoutOverlapping();
        $schedule->command('post:publish')
            ->everyTenMinutes()
            ->withoutOverlapping();
    })
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(function (): void {
                    require base_path('routes/Api/V1/api.php');

                    Route::middleware(['auth:staff', 'admin.audit'])
                        ->prefix('admin')
                        ->name('admin.')
                        ->group(function (): void {
                            require base_path('routes/Api/V1/admin/admin.php');
                        });

                    Route::prefix('shop')
                        ->name('shop.')
                        ->group(function (): void {
                            require base_path('routes/Api/V1/shop/shop.php');
                        });
                });
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

        // Register custom middleware aliases
        $middleware->alias([
            'admin.audit' => App\Http\Middleware\AdminAuditMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = function (Request $request) {
            return $request->expectsJson()
                || $request->is('api/*')
                || str_starts_with($request->path(), 'api/');
        };

        $exceptions->renderable(function (App\Exceptions\InvalidJalaliDateException $e, $request) use ($isApiRequest): void {
            // Check if the request expects a JSON response (typical for APIs)
            if ($isApiRequest($request)) {
                // Throw a standard Laravel ValidationException
                // This will automatically generate a 422 response
                throw ValidationException::withMessages([
                    $e->property => [$e->getMessage()], // Use the property name from the exception
                ]);
            }
        });
        // 1. ValidationException (422 Unprocessable Entity)
        $exceptions->renderable(function (ValidationException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                // Use your specific macro for validation errors
                return response()->validationErrors($e->errors());
            }

            return null;
        });

        // 2. AuthenticationException (401 Unauthorized)
        $exceptions->renderable(function (AuthenticationException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                // Use your 'unauthorized' macro
                return response()->unauthorized(__('messages.unauthorized'), $e);
            }

            return null;
        });

        // 3. AccessDeniedHttpException (403 Forbidden)
        // (e.g., from Gate denials or Policies if user is authenticated but not authorized)
        $exceptions->renderable(function (AccessDeniedHttpException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                return response()->forbidden(__('messages.forbidden'), $e);
            }

            return null;
        });

        // 4. ModelNotFoundException (Typically leads to 404)
        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                return response()->notFound(model: $e->getModel());
            }

            return null;
        });

        // 5. NotFoundHttpException (404 Not Found - for routes or other non-model 404s)
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) use ($isApiRequest) {
            if ($isApiRequest($request)) {
                return response()->notFound();
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
            $isLikelySafeToUseConfig = ! ($e instanceof ParseError); // Basic check
            $isDebug                 = false; // Default to not debug for safety

            if ($isLikelySafeToUseConfig && app()->bound('config')) {
                try {
                    $isDebug = config('app.debug', false);
                } catch (Throwable $configEx) {
                    // Config access failed, assume not debug mode for safety, or log this specific failure.
                    // error_log("Error handler: Failed to access config: " . $configEx->getMessage());
                    $isDebug = false; // Or true, if you prefer to default to verbose on config failure
                }
            } elseif ($e instanceof ParseError) {
                // For ParseError, if we even reach here, assume debug is desired for max info,
                // but we can't trust config().
                // The actual ParseError message will be the most valuable.
                $isDebug = true;
            }

            if ($isApiRequest($request)) {
                $responsePayload = [];
                $baseMessage     = 'An internal server error occurred.'; // Simplest fallback

                // Attempt to get a localized message only if config seems safe
                if ($isLikelySafeToUseConfig && app()->bound('config') && function_exists('__')) {
                    try {
                        $baseMessage = __('messages.server_error');
                    } catch (Throwable $localizationError) {
                        // Could log $localizationError if $isLikelySafeToUseConfig
                    }
                }

                $responsePayload['message'] = ($isDebug && ! ($e instanceof ParseError)) ? $e->getMessage() : $baseMessage;
                // For ParseError, $e->getMessage() IS the critical info.
                if ($e instanceof ParseError) {
                    $responsePayload['message'] = 'Parse Error: '.$e->getMessage();
                }

                if ($isDebug) {
                    $responsePayload['debug'] = [
                        'exception_class' => get_class($e),
                        'message'         => $e->getMessage(), // Can be redundant with top-level message
                        'file'            => $e->getFile(),
                        'line'            => $e->getLine(),
                    ];
                    // For ParseError, trace might not be very useful or available.
                    // Only add trace if not a ParseError and isDebug.
                    if (! ($e instanceof ParseError)) {
                        try {
                            $traceString = $e->getTraceAsString();
                            json_encode($traceString); // Test encodability
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $responsePayload['debug']['trace'] = $traceString;
                            } else {
                                $responsePayload['debug']['trace'] = 'Trace not encodable: '.json_last_error_msg();
                            }
                        } catch (Throwable $traceEx) {
                            $responsePayload['debug']['trace_error'] = 'Could not get trace: '.$traceEx->getMessage();
                        }
                    }
                }

                // Attempt to send JSON. If json_encode itself fails, this might still be empty.
                try {
                    return response()->json($responsePayload, 500);
                } catch (Throwable $jsonException) {
                    // VERY last resort if even building the JsonResponse fails.
                    // error_log("FATAL: Could not construct JSON response in error handler: " . $jsonException->getMessage());
                    // Return a plain text response as Symfony Response might be available
                    return new Symfony\Component\HttpFoundation\Response(
                        'Internal Server Error. Details unavailable. Check server logs. Exception: '.get_class($e),
                        500,
                        ['Content-Type' => 'text/plain']
                    );
                }
            }

            return null;
        });

    })->create();
