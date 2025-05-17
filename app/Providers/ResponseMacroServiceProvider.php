<?php

namespace App\Providers;

use App\Contracts\ApiResponseInterface;
use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiFailResponse;
use App\Http\Responses\ApiSuccessResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ResponseMacroServiceProvider extends ServiceProvider
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
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('success',
            function (mixed $data = null, string $message = 'Operation successful.'): ApiResponseInterface {

                return new ApiSuccessResponse($message, $data);
            });

        /**
         * Resource created response (201 Created)
         *
         * @param  mixed|null  $data  Created resource data (optional).
         * @param  string  $message  Success message (optional).
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('created',
            function (mixed $data = null, string $message = 'Resource created successfully.'): ApiResponseInterface {
                return new ApiSuccessResponse($message, $data, HttpStatus::HTTP_CREATED);
            });

        /**
         * No content response (204 No Content)
         * Used for successful actions that don't return a body (e.g., delete).
         *
         * @return \Illuminate\Http\JsonResponse
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
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('error',
            function (string $message, int $status = HttpStatus::HTTP_BAD_REQUEST,mixed $errors = null): ApiResponseInterface {
                return new ApiFailResponse($message, $errors, $status);
            });

        /**
         * Validation error response (422 Unprocessable Entity)
         *
         * @param  \Illuminate\Contracts\Validation\Validator|array  $errors  Validator instance or errors array.
         * @param  string  $message  Error message (optional).
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('validationError',
            function (string $message = 'The given data was invalid.') use ($responseFactory): ApiResponseInterface {
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
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('validationErrors',
            function (mixed $errors, string $message = 'The given data was invalid.'): ApiResponseInterface {
                $errorPayload = ($errors instanceof Validator) ? $errors->errors()->toArray() : $errors;

                return new ApiFailResponse($message, $errorPayload, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
            });

        /**
         * Not Found response (404 Not Found)
         *
         * @param  string  $message  Error message (optional).
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('notFound',
            function (string $message = 'Resource not found.') use ($responseFactory): ApiResponseInterface {
                return $responseFactory->error($message, HttpStatus::HTTP_NOT_FOUND);
            });

        /**
         * Forbidden response (403 Forbidden)
         *
         * @param  string  $message  Error message (optional).
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('forbidden',
            function (string $message = 'This action is forbidden.') use ($responseFactory): ApiResponseInterface {
                return $responseFactory->error($message, HttpStatus::HTTP_FORBIDDEN);
            });

        /**
         * Unauthorized response (401 Unauthorized)
         *
         * @param  string  $message  Error message (optional).
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('unauthorized',
            function (string $message = 'Authentication required.') use ($responseFactory): ApiResponseInterface {
                return $responseFactory->error($message, HttpStatus::HTTP_UNAUTHORIZED);
            });

        /**
         * Method Not Allowed response (405 Method Not Allowed)
         *
         * @param  string  $message  Error message (optional).
         * @return \Illuminate\Http\JsonResponse
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
         * @return \Illuminate\Http\JsonResponse
         */
        $responseFactory->macro('serverError',
            function (string $message = 'An internal server error occurred.', ?\Throwable $exception = null): ApiResponseInterface {
                // In production, you might want to log the actual error but return a generic message.
                // You could add logic here based on app environment.
                return new ApiErrorResponse($message, $exception, HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
            });
    }
}
