<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Contracts\ApiResponseInterface;
use App\Contracts\CartIdentifier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Throwable;

final class ApiResponseService
{
    /**
     * Get guest token headers if applicable.
     */
    /**
     * @return array<string, mixed>
     */
    public function getGuestHeaders(): array
    {
        /** @var CartIdentifier $identifier */
        $identifier = app(CartIdentifier::class);

        // Authenticated users don't need a guest token header
        if ($identifier->userId() !== null) {
            return [];
        }

        // Ensure a token exists for guests so the client can persist it
        $token = $identifier->guestToken() ?? $identifier->ensureGuestToken();

        return $token ? ['X-Guest-Token' => $token] : [];
    }

    /**
     * Standard success response (200 OK)
     */
    public function success(mixed $data = null, ?string $message = null, int $code = HttpStatus::HTTP_OK): ApiResponseInterface
    {
        $message ??= __('messages.success');

        return new ApiSuccessResponse($message, $data, $code, [], $this->getGuestHeaders());
    }

    /**
     * Resource created response (201 Created)
     */
    public function created(mixed $data = null, ?string $message = null, null|object|string $model = null): ApiResponseInterface
    {
        if (! $message) {
            $message = $model
                ? __('messages.created', ['model' => get_model_label($model)])
                : __('messages.created', ['model' => null]);
        }

        return new ApiSuccessResponse($message, $data, HttpStatus::HTTP_CREATED, [], $this->getGuestHeaders());
    }

    /**
     * Resource updated response (200 OK)
     */
    public function updated(mixed $data = null, ?string $message = null, null|object|string $model = null): ApiResponseInterface
    {
        if (! $message) {
            $message = $model
                ? __('messages.updated', ['model' => get_model_label($model)])
                : __('messages.updated', ['model' => null]);
        }

        return new ApiSuccessResponse($message, $data, HttpStatus::HTTP_OK, [], $this->getGuestHeaders());
    }

    /**
     * No content response (204 No Content)
     * Used for successful actions that don't return a body (e.g., delete).
     */
    public function noContentJson(): JsonResponse
    {
        // Strictly, 204 should have NO body, json(null) ensures correct Content-Type header if needed
        return response()->json(null, HttpStatus::HTTP_NO_CONTENT, $this->getGuestHeaders());
    }

    // --- CLIENT ERROR RESPONSES ---

    /**
     * Generic error response (Default: 400 Bad Request)
     *
     * @param  array<string, string>  $headers
     */
    public function error(string $message = '', int $status = HttpStatus::HTTP_BAD_REQUEST, mixed $errors = null, array $headers = []): ApiResponseInterface
    {
        $message = $message ?: (string) __('messages.error');

        return new ApiFailResponse($message, $errors, $status, [], $headers);
    }

    /**
     * Validation error response (422 Unprocessable Entity)
     */
    public function validationError(?string $message = null): ApiResponseInterface
    {
        $message ??= (string) __('messages.validation_error');

        return apiResponse()->error($message, HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Validation errors response with payload (422 Unprocessable Entity)
     */
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function validationErrors(mixed $errors, ?string $message = null, ?array $metadata = []): ApiResponseInterface
    {
        $errorPayload = ($errors instanceof Validator) ? $errors->errors()->toArray() : $errors;
        $message ??= (string) __('messages.validation_error');

        return new ApiFailResponse($message, $errorPayload, HttpStatus::HTTP_UNPROCESSABLE_ENTITY, $metadata);
    }

    /**
     * Not Found response (404 Not Found)
     */
    public function notFound(?string $message = null, null|object|string $model = null): ApiResponseInterface
    {
        if (! $message) {
            $message = $model
                ? __('messages.not_found', ['model' => get_model_label($model)])
                : __('messages.resource_not_found');
        }

        return apiResponse()->error((string) $message, HttpStatus::HTTP_NOT_FOUND);
    }

    /**
     * Forbidden response (403 Forbidden)
     */
    public function forbidden(string $message = '', mixed $errors = null): ApiResponseInterface
    {
        $message = $message ?: (string) __('messages.forbidden');

        return apiResponse()->error($message, HttpStatus::HTTP_FORBIDDEN, $errors);
    }

    /**
     * Unauthorized response (401 Unauthorized)
     */
    public function unauthorized(string $message = '', mixed $errors = null): ApiResponseInterface
    {
        $message = $message ?: (string) __('messages.unauthenticated');

        return apiResponse()->error($message, HttpStatus::HTTP_UNAUTHORIZED, $errors);
    }

    /**
     * Method Not Allowed response (405 Method Not Allowed)
     */
    public function methodNotAllowed(string $message = ''): ApiResponseInterface
    {
        $message = $message ?: (string) __('messages.method_not_allowed');

        return apiResponse()->error($message, HttpStatus::HTTP_METHOD_NOT_ALLOWED);
    }

    // --- SERVER ERROR RESPONSES ---

    /**
     * Internal Server Error response (500 Internal Server Error)
     */
    public function serverError(string $message = '', ?Throwable $exception = null): ApiResponseInterface
    {
        $message = $message ?: (string) __('messages.server_error');

        return new ApiErrorResponse($message, $exception, HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
    }
}
