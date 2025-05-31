<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ApiResponseInterface;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiFailResponse;
use App\Http\Responses\ApiSuccessResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Throwable;

final class ResponseMacroServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $responseFactory = Response::getFacadeRoot();

        /**
         * Standard success response (200 OK)
         *
         * @param  mixed|null  $data  Data payload (optional).
         * @param  string  $message  Success message (optional).
         * @return JsonResponse
         */
        $responseFactory->macro('success',
            function (mixed $data = null, ?string $message = null): ApiResponseInterface {
                if (!$message) {
                    $message = __('messages.success');
                }
                return new ApiSuccessResponse($message, $data);
            });

        /**
         * Resource created response (201 Created)
         *
         * @param  mixed|null  $data  Created resource data (optional).
         * @param  string  $message  Success message (optional).
         * @param  null|object|string  $model  Model class or instance
         * @return JsonResponse
         */
        $responseFactory->macro('created',
            function (mixed $data = null, ?string $message = null,null|object|string $model = null): ApiResponseInterface {
                if (!$message && !$model) {
                    $message = __('messages.created', ['model' => null]);
                }
                if (!$message && $model) {
                    $message = __('messages.created', ['model' => get_model_label($model)]);
                }
                return new ApiSuccessResponse($message, $data, HttpStatus::HTTP_CREATED);
            });

        /**
         * Resource updated response (200 success)
         *
         * @param  mixed|null  $data  Updated resource data (optional).
         * @param  string  $message  Success message (optional).
         * @param  null|object|string  $model  Model class or instance
         * @return JsonResponse
         */
        $responseFactory->macro('updated',
            function (mixed $data = null, ?string $message = null,null|object|string $model = null): ApiResponseInterface {
                if (!$message && !$model) {
                    $message = __('messages.updated', ['model' => null]);
                }
                if (!$message && $model) {
                    $message = __('messages.updated', ['model' => get_model_label($model)]);
                }
                return new ApiSuccessResponse($message, $data);
            });

        /**
         * No content response (204 No Content)
         * Used for successful actions that don't return a body (e.g., delete).
         *
         * @return JsonResponse
         */
        $responseFactory->macro('noContentJson', function () use ($responseFactory): JsonResponse {
            // Strictly, 204 should have NO body, json(null) ensures correct Content-Type header if needed
            return $responseFactory->json(null, HttpStatus::HTTP_NO_CONTENT);
        });

        // --- CLIENT ERROR RESPONSES ---

        /**
         * Generic error response (Default: 400 Bad Request)
         *
         * @param  string  $message  Error message.
         * @param  int  $status  HTTP status code.
         * @param  mixed|null  $errors  Optional detailed errors.
         * @return JsonResponse
         */
        $responseFactory->macro('error',
            function (string $message, int $status = HttpStatus::HTTP_BAD_REQUEST, mixed $errors = null): ApiResponseInterface {
                $message = $message ?: __('messages.error');
                return new ApiFailResponse($message, $errors, $status);
            });

        /**
         * Validation error response (422 Unprocessable Entity)
         *
         * @param  \Illuminate\Contracts\Validation\Validator|array  $errors  Validator instance or errors array.
         * @param  string  $message  Error message (optional).
         * @return JsonResponse
         */
        $responseFactory->macro('validationError',
            function (?string $message = null) use ($responseFactory): ApiResponseInterface {
                if (!$message) {
                    $message = __('messages.validation_error');
                }
                return $responseFactory->error( // Reuse the generic error macro
                    $message,
                    HttpStatus::HTTP_UNPROCESSABLE_ENTITY
                );
            });

        /**
         * Validation error response (422 Unprocessable Entity)
         *
         * @param  \Illuminate\Contracts\Validation\Validator|array  $errors  Validator instance or errors array.
         * @param  string  $message  Error message (optional).
         * @return JsonResponse
         */
        $responseFactory->macro('validationErrors',
            function (mixed $errors, ?string $message = null): ApiResponseInterface {
                $errorPayload = ($errors instanceof \Illuminate\Contracts\Validation\Validator) ? $errors->errors()->toArray() : $errors;
                if (!$message) {
                    $message = __('messages.validation_error');
                }
                return new ApiFailResponse($message, $errorPayload, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
            });

        /**
         * Not Found response (404 Not Found)
         *
         * @param  string  $message  Error message (optional).
         * @return JsonResponse
         */
        $responseFactory->macro('notFound',
            function (?string $message = null, null|object|string $model = null) use ($responseFactory): ApiResponseInterface {
                if (!$message && !$model) {
                    $message = __('messages.resource_not_found');
                }

                if (!$message && $model) {
                    $message = __('messages.not_found', ['model' => get_model_label($model)]);
                }
                return $responseFactory->error($message, HttpStatus::HTTP_NOT_FOUND);
            });

        /**
         * Forbidden response (403 Forbidden)
         *
         * @param  string  $message  Error message (optional).
         * @return JsonResponse
         */
        $responseFactory->macro('forbidden',
            function (string $message = 'This action is forbidden.', mixed $errors = null) use ($responseFactory): ApiResponseInterface {
                return $responseFactory->error($message, HttpStatus::HTTP_FORBIDDEN, $errors);
            });

        /**
         * Unauthorized response (401 Unauthorized)
         *
         * @param  string  $message  Error message (optional).
         * @return JsonResponse
         */
        $responseFactory->macro('unauthorized',
            function (string $message = 'Authentication required.', mixed $errors = null) use ($responseFactory): ApiResponseInterface {
                return $responseFactory->error($message, HttpStatus::HTTP_UNAUTHORIZED, $errors);
            });

        /**
         * Method Not Allowed response (405 Method Not Allowed)
         *
         * @param  string  $message  Error message (optional).
         * @return JsonResponse
         */
        $responseFactory->macro('methodNotAllowed',
            function (string $message = 'Method not allowed for this resource.') use ($responseFactory): ApiResponseInterface {
                return $responseFactory->error($message, HttpStatus::HTTP_METHOD_NOT_ALLOWED);
            });

        // --- SERVER ERROR RESPONSES ---

        /**
         * Internal Server Error response (500 Internal Server Error)
         *
         * @param  string  $message  Error message (optional).
         * @param  mixed|null  $errors  Optional detailed errors (use cautiously in production).
         * @return JsonResponse
         */
        $responseFactory->macro('serverError',
            function (string $message = 'An internal server error occurred.', ?Throwable $exception = null): ApiResponseInterface {
                // In production, you might want to log the actual error but return a generic message.
                // You could add logic here based on app environment.
                return new ApiErrorResponse($message, $exception, HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
            });
    }

}
