<?php

declare(strict_types=1);

use App\Contracts\ApiResponseInterface;
use App\Exceptions\CustomValidationException;
use App\Exceptions\Gateway\MellatException;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\ResourceNotProvisionedException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Exceptions\RefundGatewayException;
use App\Exceptions\RefundValidationException;
use App\Http\Middleware\AdminAuditMiddleware;
use App\Http\Middleware\AuthenticateTokenFromCookie;
use App\Http\Middleware\EnsureAdminNumericIdsMiddleware;
use App\Http\Middleware\ProfileCheckMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
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

        // Price indexing and featured price checks
        $schedule->command('prices:check-expired-featured')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

        // Full price reindex (both JSON cache AND price index table)
        $schedule->command('prices:index-all')
            ->twiceDailyAt(2, 14, 3)
            ->withoutOverlapping();

        $schedule->command('products:index-availability')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('horizon:snapshot')
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping();

        // Cancel abandoned pending orders (runs every 10 minutes, cancels orders older than 30 minutes)
        $schedule->command('orders:cancel-abandoned --timeout=30')
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

                    Route::middleware(['auth.cookie:staff', 'auth:staff', 'admin.audit'])
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
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            EnsureAdminNumericIdsMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/webhooks/github-deployer',
        ]);
        $middleware->redirectGuestsTo(function (Request $request): null {
            if ($request->is('api/*') || $request->is('admin/*') || $request->expectsJson()) {
                return null;
            }

            return null;
        });

        // Register custom middleware aliases
        $middleware->alias([
            'admin.audit'   => AdminAuditMiddleware::class,
            'profile.check' => ProfileCheckMiddleware::class,
            'auth.cookie'   => AuthenticateTokenFromCookie::class,
        ]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, AuthenticateTokenFromCookie::class);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Suppress expected/retryable exceptions from Sentry to reduce noise.
        $exceptions->dontReport([
            MellatException::class,
            RecoverableProvisioningException::class,
            UnrecoverableProvisioningException::class,
        ]);

        $isApiRequest = function (Request $request): bool {
            return $request->expectsJson()
                || $request->is('api/*')
                || str_starts_with($request->path(), 'api/');
        };

        // 1. ValidationException (422 Unprocessable Entity)
        $exceptions->renderable(function (ValidationException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                // Use your specific macro for validation errors
                return apiResponse()->validationErrors($e->errors());
            }

            return null;
        });

        // 2. AuthenticationException (401 Unauthorized)
        $exceptions->renderable(function (AuthenticationException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                // Use your 'unauthorized' macro
                return apiResponse()->unauthorized(__('messages.unauthorized'), $e);
            }

            return null;
        });

        // 3. AccessDeniedHttpException (403 Forbidden)
        // (e.g., from Gate denials or Policies if user is authenticated but not authorized)
        $exceptions->renderable(function (AccessDeniedHttpException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->forbidden(__('messages.forbidden'), $e);
            }

            return null;
        });

        // 4. ModelNotFoundException (Typically leads to 404)
        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->notFound(model: $e->getModel());
            }

            return null;
        });

        // 5. NotFoundHttpException (404 Not Found - for routes or other non-model 404s)
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->notFound($e->getMessage());
            }

            return null;
        });

        // 6. MethodNotAllowedHttpException (405 Method Not Allowed)
        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->methodNotAllowed($e->getMessage() ?: (string) __('messages.method_not_allowed'));
            }

            return null;
        });

        // 6.5. RefundValidationException (422 Unprocessable Entity)
        $exceptions->renderable(function (RefundValidationException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->error($e->getMessage(), 422);
            }

            return null;
        });

        // 6.6. RefundGatewayException (502 Bad Gateway — payment gateway failures)
        $exceptions->renderable(function (RefundGatewayException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->error($e->getMessage(), 502);
            }

            return null;
        });

        // 6.7. MellatException (502 Bad Gateway — bank gateway errors)
        $exceptions->renderable(function (MellatException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->error($e->getMessage(), 502);
            }

            return null;
        });

        // 6.8. CustomValidationException (422 Unprocessable Entity — service-layer validation failures)
        $exceptions->renderable(function (CustomValidationException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->validationError($e->getMessage());
            }

            return null;
        });

        // 6.9. ExternalProvisioningException hierarchy (provisioning integration errors)
        // Covers: ResourceNotProvisioned(404), RecoverableProvisioning(503), UnrecoverableProvisioning(500)
        $exceptions->renderable(function (ExternalProvisioningException $e, Request $request) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                $status = $e instanceof ResourceNotProvisionedException ? 404 : 503;

                return apiResponse()->error($e->getMessage(), $status);
            }

            return null;
        });

        // 7. Other generic HttpExceptions (e.g., 419 CSRF, 400 Bad Request not caught by others)
        $exceptions->renderable(function (
            Symfony\Component\HttpKernel\Exception\HttpException $e,
            Request $request
        ) use ($isApiRequest): ?ApiResponseInterface {
            if ($isApiRequest($request)) {
                return apiResponse()->error($e->getMessage(), $e->getStatusCode());
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
                $baseMessage     = (string) __('messages.server_error'); // Simplest fallback

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
                    $responsePayload['message'] = __('messages.parse_error').': '.$e->getMessage();
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
                        (string) __('messages.server_error_details',
                            ['class' => get_class($e)]
                        ),
                        500,
                        ['Content-Type' => 'text/plain']
                    );
                }
            }

            return null;
        });

    })->create();
