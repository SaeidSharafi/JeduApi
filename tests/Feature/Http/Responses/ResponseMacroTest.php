<?php

use Symfony\Component\HttpFoundation\Response;

test('success response returns correct structure', function (): void {
    $data = ['key' => 'value'];
    $message = 'Test success message';

    $response = response()->success($data, $message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('data', $data)
        ->toHaveKey('metadata');
});

test('created response returns 201 status code', function (): void {
    $data = ['id' => 1];
    $message = 'Resource created';

    $response = response()->created($data, $message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_CREATED)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('data', $data)
        ->toHaveKey('metadata');
});

test('no content response returns 204 status code', function (): void {
    $response = response()->noContentJson();

    expect($response->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($response->getContent())->toBe('{}');
});

test('error response returns correct structure', function (): void {
    $message = 'Test error message';
    $errors = ['field' => 'error details'];

    $response = response()->error($message, Response::HTTP_BAD_REQUEST, $errors)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('errors', $errors)
        ->toHaveKey('metadata');
});

test('validation error response returns 422 status code', function (): void {
    $message = 'Validation failed';

    $response = response()->validationError($message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('errors')
        ->toHaveKey('metadata');
});

test('validation errors response includes error details', function (): void {
    $errors = ['email' => ['Invalid email format']];
    $message = 'Validation failed';

    $response = response()->validationErrors($errors, $message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('errors', $errors)
        ->toHaveKey('metadata');
});

test('not found response returns 404 status code', function (): void {
    $message = 'Resource not found';

    $response = response()->notFound($message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('errors')
        ->toHaveKey('metadata');
});

test('forbidden response returns 403 status code', function (): void {
    $message = 'Access denied';

    $response = response()->forbidden($message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('errors')
        ->toHaveKey('metadata');
});

test('unauthorized response returns 401 status code', function (): void {
    $message = 'Unauthorized access';

    $response = response()->unauthorized($message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('errors')
        ->toHaveKey('metadata');
});

test('method not allowed response returns 405 status code', function (): void {
    $message = 'Method not allowed';

    $response = response()->methodNotAllowed($message)->toResponse(request());

    expect($response->getStatusCode())->toBe(Response::HTTP_METHOD_NOT_ALLOWED)
        ->and(json_decode($response->getContent(), true))
        ->toHaveKey('message', $message)
        ->toHaveKey('errors')
        ->toHaveKey('metadata');
});

test('server error response returns 500 status code', function (): void {
    $message = 'Server error occurred';
    $exception = new Exception('Test exception');

    $response = response()->serverError($message, $exception)->toResponse(request());

    $responseData = json_decode($response->getContent(), true);
    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($responseData)->toHaveKey('message', $message);

    if (config('app.debug')) {
        expect($responseData)->toHaveKey('debug');
    }
});
