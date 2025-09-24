<?php

declare(strict_types=1);

use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiFailResponse;
use App\Http\Responses\ApiSuccessResponse;
use App\Providers\ResponseMacroServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Symfony\Component\HttpFoundation\Response as HttpStatus; // Required for request() helper

if (! function_exists('get_model_label')) {
    function get_model_label(object|string $class): string
    {
        if (is_object($class) || class_exists($class)) {
            return __('messages.models.'.mb_strtolower(class_basename($class)));
        }
        if (is_string($class)) {
            return __('messages.models.'.mb_strtolower($class));
        }

        return '';
    }
}

// A dummy model for testing purposes
if (! class_exists('App\Models\TestDummyModel')) {
    class_alias(stdClass::class, 'App\Models\TestDummyModel');
}

describe('ResponseMacroServiceProvider', function (): void {
    beforeEach(function (): void {
        // Ensure a fresh request object for each test if request() helper is used.
        $this->app->singleton('request', fn (): Request => Request::create('/test', 'GET'));
        $this->app->register(ResponseMacroServiceProvider::class);
    });

    it('registers and uses success macro correctly', function (): void {
        $data = ['id' => 1, 'name' => 'Test'];

        // Test with data and custom message
        $apiResponse = Response::success($data, 'Resource fetched successfully.');
        expect($apiResponse)->toBeInstanceOf(ApiSuccessResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_OK);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Resource fetched successfully.')
            ->and($responseData['data'])->toBe($data)
            ->and($responseData['metadata'])->toBe([]);

        // Test with data and default message
        $apiResponseDefault  = Response::success($data);
        $jsonResponseDefault = $apiResponseDefault->toResponse(request());
        $responseDataDefault = $jsonResponseDefault->getData(true);
        expect($responseDataDefault['message'])->toBe(__('messages.success'))
            ->and($responseDataDefault['data'])->toBe($data)
            ->and($responseDataDefault['metadata'])->toBe([]);

        // Test with null data and custom message
        $apiResponseNull  = Response::success(null, 'Action completed.');
        $jsonResponseNull = $apiResponseNull->toResponse(request());
        $responseDataNull = $jsonResponseNull->getData(true);
        expect($responseDataNull['message'])->toBe('Action completed.')
            ->and($responseDataNull['data'])->toBeNull()
            ->and($responseDataNull['metadata'])->toBe([]);

        // Test with null data and default message
        $apiResponseNullDefault  = Response::success();
        $jsonResponseNullDefault = $apiResponseNullDefault->toResponse(request());
        $responseDataNullDefault = $jsonResponseNullDefault->getData(true);
        expect($responseDataNullDefault['message'])->toBe(__('messages.success'))
            ->and($responseDataNullDefault['data'])->toBeNull()
            ->and($responseDataNullDefault['metadata'])->toBe([]);
    });

    it('registers and uses created macro correctly', function (): void {
        $data       = ['id' => 1, 'name' => 'New Resource'];
        $modelClass = App\Models\TestDummyModel::class;
        $modelLabel = get_model_label($modelClass); // "test dummy model"

        // Test with data, custom message, and model
        $apiResponse = Response::created($data, 'Item created!', $modelClass);
        expect($apiResponse)->toBeInstanceOf(ApiSuccessResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_CREATED);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Item created!')
            ->and($responseData['data'])->toBe($data)
            ->and($responseData['metadata'])->toBe([]);

        // Test with data and model (default message)
        $apiResponseModel  = Response::created($data, null, $modelClass);
        $jsonResponseModel = $apiResponseModel->toResponse(request());
        $responseDataModel = $jsonResponseModel->getData(true);
        expect($responseDataModel['message'])->toBe(__('messages.created', ['model' => $modelLabel]))
            ->and($responseDataModel['data'])->toBe($data)
            ->and($responseDataModel['metadata'])->toBe([]);

        // Test with data and no model (default message)
        $apiResponseNoModel  = Response::created($data);
        $jsonResponseNoModel = $apiResponseNoModel->toResponse(request());
        $responseDataNoModel = $jsonResponseNoModel->getData(true);
        expect($responseDataNoModel['message'])->toBe(__('messages.created', ['model' => null]))
            ->and($responseDataNoModel['data'])->toBe($data)
            ->and($responseDataNoModel['metadata'])->toBe([]);

        // Test with null data and custom message (no model)
        $apiResponseNullData  = Response::created(null, 'Resource successfully made.');
        $jsonResponseNullData = $apiResponseNullData->toResponse(request());
        $responseDataNullData = $jsonResponseNullData->getData(true);
        expect($responseDataNullData['message'])->toBe('Resource successfully made.')
            ->and($responseDataNullData['data'])->toBeNull()
            ->and($responseDataNullData['metadata'])->toBe([]);
    });

    it('registers and uses updated macro correctly', function (): void {
        $data       = ['id' => 1, 'name' => 'Updated Resource'];
        $modelClass = App\Models\TestDummyModel::class;
        $modelLabel = get_model_label($modelClass);

        // Test with data, custom message, and model
        $apiResponse = Response::updated($data, 'Item updated!', $modelClass);
        expect($apiResponse)->toBeInstanceOf(ApiSuccessResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_OK);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Item updated!')
            ->and($responseData['data'])->toBe($data)
            ->and($responseData['metadata'])->toBe([]);

        // Test with data and model (default message)
        $apiResponseModel  = Response::updated($data, null, $modelClass);
        $jsonResponseModel = $apiResponseModel->toResponse(request());
        $responseDataModel = $jsonResponseModel->getData(true);
        expect($responseDataModel['message'])->toBe(__('messages.updated', ['model' => $modelLabel]))
            ->and($responseDataModel['data'])->toBe($data)
            ->and($responseDataModel['metadata'])->toBe([]);

        // Test with data and no model (default message)
        $apiResponseNoModel  = Response::updated($data);
        $jsonResponseNoModel = $apiResponseNoModel->toResponse(request());
        $responseDataNoModel = $jsonResponseNoModel->getData(true);
        expect($responseDataNoModel['message'])->toBe(__('messages.updated', ['model' => null]))
            ->and($responseDataNoModel['data'])->toBe($data)
            ->and($responseDataNoModel['metadata'])->toBe([]);

        // Test with null data and custom message (no model)
        $apiResponseNullData  = Response::updated(null, 'Resource successfully modified.');
        $jsonResponseNullData = $apiResponseNullData->toResponse(request());
        $responseDataNullData = $jsonResponseNullData->getData(true);
        expect($responseDataNullData['message'])->toBe('Resource successfully modified.')
            ->and($responseDataNullData['data'])->toBeNull()
            ->and($responseDataNullData['metadata'])->toBe([]);
    });

    it('registers and uses noContentJson macro correctly', function (): void {
        $response = Response::noContentJson();
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(HttpStatus::HTTP_NO_CONTENT);
    });

    it('registers and uses error macro correctly', function (): void {
        // Test with message and default status
        $apiResponse = Response::error('A generic error occurred');
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_BAD_REQUEST);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('A generic error occurred')
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with message, custom status, and errors
        $errors             = ['field' => ['Error detail']];
        $apiResponseCustom  = Response::error('Specific error', HttpStatus::HTTP_NOT_IMPLEMENTED, $errors);
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        expect($jsonResponseCustom->getStatusCode())->toBe(HttpStatus::HTTP_NOT_IMPLEMENTED);
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('Specific error')
            ->and($responseDataCustom['errors'])->toBe($errors)
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('registers and uses validationError macro correctly', function (): void {
        // Test with default message
        // Note: validationError internally calls `Response::error`, so the returned object is ApiFailResponse
        $apiResponse = Response::validationError();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class); // Because it calls error() which returns ApiFailResponse
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.validation_error'))
            ->and($responseData['errors'])->toBeNull() // error() macro sets errors to null if not provided
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message
        $apiResponseCustom  = Response::validationError('Your input is not valid.');
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('Your input is not valid.')
            ->and($responseDataCustom['errors'])->toBeNull()
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('registers and uses validationErrors macro correctly', function (): void {
        $validationErrors = ['email' => ['The email field is required.']];

        // Test with errors array and default message
        $apiResponse = Response::validationErrors($validationErrors);
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.validation_error'))
            ->and($responseData['errors'])->toBe($validationErrors)
            ->and($responseData['metadata'])->toBe([]);

        // Test with Validator instance and custom message
        $validator = ValidatorFacade::make([], ['name' => 'required']);
        $validator->fails(); // Trigger error collection
        $apiResponseValidator  = Response::validationErrors($validator, 'Custom validation message');
        $jsonResponseValidator = $apiResponseValidator->toResponse(request());
        $responseDataValidator = $jsonResponseValidator->getData(true);
        expect($responseDataValidator['message'])->toBe('Custom validation message')
            ->and($responseDataValidator['errors'])->toEqual($validator->errors()->toArray())
            ->and($responseDataValidator['metadata'])->toBe([]);
    });

    it('registers and uses notFound macro correctly', function (): void {
        $modelClass = App\Models\TestDummyModel::class;
        $modelLabel = get_model_label($modelClass);

        // Test with model and default message
        $apiResponse = Response::notFound(null, $modelClass);
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class); // Because it calls error()
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_NOT_FOUND);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.not_found', ['model' => $modelLabel]))
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message
        $apiResponseCustom  = Response::notFound('The item you are looking for does not exist.');
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('The item you are looking for does not exist.')
            ->and($responseDataCustom['errors'])->toBeNull()
            ->and($responseDataCustom['metadata'])->toBe([]);

        // Test without model (generic message)
        $apiResponseGeneric  = Response::notFound();
        $jsonResponseGeneric = $apiResponseGeneric->toResponse(request());
        $responseDataGeneric = $jsonResponseGeneric->getData(true);
        expect($responseDataGeneric['message'])->toBe(__('messages.resource_not_found'))
            ->and($responseDataGeneric['errors'])->toBeNull()
            ->and($responseDataGeneric['metadata'])->toBe([]);
    });

    it('registers and uses forbidden macro correctly', function (): void {
        // Test with default message
        $apiResponse = Response::forbidden();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class); // Because it calls error()
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_FORBIDDEN);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('This action is forbidden.')
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message and errors
        $errors             = ['permission' => ['Missing required permission']];
        $apiResponseCustom  = Response::forbidden('You shall not pass!', $errors);
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('You shall not pass!')
            ->and($responseDataCustom['errors'])->toBe($errors)
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('registers and uses unauthorized macro correctly', function (): void {
        // Test with default message
        $apiResponse = Response::unauthorized();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class); // Because it calls error()
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_UNAUTHORIZED);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Authentication required.')
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message and errors
        $errors             = ['token' => ['Invalid token']];
        $apiResponseCustom  = Response::unauthorized('Please log in.', $errors);
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('Please log in.')
            ->and($responseDataCustom['errors'])->toBe($errors)
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('registers and uses methodNotAllowed macro correctly', function (): void {
        // Test with default message
        $apiResponse = Response::methodNotAllowed();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class); // Because it calls error()
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_METHOD_NOT_ALLOWED);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Method not allowed for this resource.')
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message
        $apiResponseCustom  = Response::methodNotAllowed('This HTTP method is not supported here.');
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('This HTTP method is not supported here.')
            ->and($responseDataCustom['errors'])->toBeNull()
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('registers and uses serverError macro correctly', function (): void {
        $originalDebug = config('app.debug'); // Store original debug state

        // Test with default message, app.debug = false
        config(['app.debug' => false]);
        $apiResponse = Response::serverError();
        expect($apiResponse)->toBeInstanceOf(ApiErrorResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('An internal server error occurred.');
        expect($responseData)->not->toHaveKey('debug');

        // Test with custom message and exception, app.debug = true
        config(['app.debug' => true]);
        $exception     = new RuntimeException('Something broke');
        $apiResponseEx = Response::serverError('A critical error happened.', $exception);
        expect($apiResponseEx)->toBeInstanceOf(ApiErrorResponse::class);
        $jsonResponseDebug = $apiResponseEx->toResponse(request());
        expect($jsonResponseDebug->getStatusCode())->toBe(HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
        $responseDataDebug = $jsonResponseDebug->getData(true);
        expect($responseDataDebug['message'])->toBe('A critical error happened.');
        expect($responseDataDebug)->toHaveKey('debug');
        expect($responseDataDebug['debug']['message'])->toBe('Something broke');
        expect($responseDataDebug['debug']['file'])->toBe($exception->getFile());

        // Test with custom message and exception, app.debug = false
        config(['app.debug' => false]);
        $jsonResponseNoDebug = $apiResponseEx->toResponse(request()); // Use the same $apiResponseEx
        expect($jsonResponseNoDebug->getStatusCode())->toBe(HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
        $responseDataNoDebug = $jsonResponseNoDebug->getData(true);
        expect($responseDataNoDebug['message'])->toBe('A critical error happened.');
        expect($responseDataNoDebug)->not->toHaveKey('debug');

        config(['app.debug' => $originalDebug]); // Restore original debug state
    });
});
