<?php

declare(strict_types=1);

use App\Http\Responses\ApiErrorResponse;
use App\Http\Responses\ApiFailResponse;
use App\Http\Responses\ApiSuccessResponse;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Symfony\Component\HttpFoundation\Response as HttpStatus; // Required for request() helper

// A dummy model for testing purposes
if (! class_exists('App\Models\TestDummyModel')) {
    class_alias(stdClass::class, 'App\Models\TestDummyModel');
}

describe('ApiResponseService', function (): void {
    beforeEach(function (): void {
        // Ensure a fresh request object for each test if request() helper is used.
        $this->app->singleton('request', fn (): Request => Request::create('/test', 'GET'));

        // We no longer need to register the Macro Service Provider!
        // The apiResponse() helper resolves the ApiResponseService automatically.
    });

    it('uses success method correctly', function (): void {
        $data = ['id' => 1, 'name' => 'Test'];

        // Test with data and custom message
        $apiResponse = apiResponse()->success($data, 'Resource fetched successfully.');
        expect($apiResponse)->toBeInstanceOf(ApiSuccessResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_OK);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Resource fetched successfully.')
            ->and($responseData['data'])->toBe($data)
            ->and($responseData['metadata'])->toBe([]);

        // Test with data and default message
        $apiResponseDefault  = apiResponse()->success($data);
        $jsonResponseDefault = $apiResponseDefault->toResponse(request());
        $responseDataDefault = $jsonResponseDefault->getData(true);
        expect($responseDataDefault['message'])->toBe(__('messages.success'))
            ->and($responseDataDefault['data'])->toBe($data)
            ->and($responseDataDefault['metadata'])->toBe([]);

        // Test with null data and custom message
        $apiResponseNull  = apiResponse()->success(null, 'Action completed.');
        $jsonResponseNull = $apiResponseNull->toResponse(request());
        $responseDataNull = $jsonResponseNull->getData(true);
        expect($responseDataNull['message'])->toBe('Action completed.')
            ->and($responseDataNull['data'])->toBeNull()
            ->and($responseDataNull['metadata'])->toBe([]);

        // Test with null data and default message
        $apiResponseNullDefault  = apiResponse()->success();
        $jsonResponseNullDefault = $apiResponseNullDefault->toResponse(request());
        $responseDataNullDefault = $jsonResponseNullDefault->getData(true);
        expect($responseDataNullDefault['message'])->toBe(__('messages.success'))
            ->and($responseDataNullDefault['data'])->toBeNull()
            ->and($responseDataNullDefault['metadata'])->toBe([]);
    });

    it('uses created method correctly', function (): void {
        $data       = ['id' => 1, 'name' => 'New Resource'];
        $modelClass = App\Models\TestDummyModel::class;
        $modelLabel = get_model_label($modelClass); // "test dummy model"

        // Test with data, custom message, and model
        $apiResponse = apiResponse()->created($data, 'Item created!', $modelClass);
        expect($apiResponse)->toBeInstanceOf(ApiSuccessResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_CREATED);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Item created!')
            ->and($responseData['data'])->toBe($data)
            ->and($responseData['metadata'])->toBe([]);

        // Test with data and model (default message)
        $apiResponseModel  = apiResponse()->created($data, null, $modelClass);
        $jsonResponseModel = $apiResponseModel->toResponse(request());
        $responseDataModel = $jsonResponseModel->getData(true);
        expect($responseDataModel['message'])->toBe(__('messages.created', ['model' => $modelLabel]))
            ->and($responseDataModel['data'])->toBe($data)
            ->and($responseDataModel['metadata'])->toBe([]);

        // Test with data and no model (default message)
        $apiResponseNoModel  = apiResponse()->created($data);
        $jsonResponseNoModel = $apiResponseNoModel->toResponse(request());
        $responseDataNoModel = $jsonResponseNoModel->getData(true);
        expect($responseDataNoModel['message'])->toBe(__('messages.created', ['model' => null]))
            ->and($responseDataNoModel['data'])->toBe($data)
            ->and($responseDataNoModel['metadata'])->toBe([]);

        // Test with null data and custom message (no model)
        $apiResponseNullData  = apiResponse()->created(null, 'Resource successfully made.');
        $jsonResponseNullData = $apiResponseNullData->toResponse(request());
        $responseDataNullData = $jsonResponseNullData->getData(true);
        expect($responseDataNullData['message'])->toBe('Resource successfully made.')
            ->and($responseDataNullData['data'])->toBeNull()
            ->and($responseDataNullData['metadata'])->toBe([]);
    });

    it('uses updated method correctly', function (): void {
        $data       = ['id' => 1, 'name' => 'Updated Resource'];
        $modelClass = App\Models\TestDummyModel::class;
        $modelLabel = get_model_label($modelClass);

        // Test with data, custom message, and model
        $apiResponse = apiResponse()->updated($data, 'Item updated!', $modelClass);
        expect($apiResponse)->toBeInstanceOf(ApiSuccessResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_OK);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('Item updated!')
            ->and($responseData['data'])->toBe($data)
            ->and($responseData['metadata'])->toBe([]);

        // Test with data and model (default message)
        $apiResponseModel  = apiResponse()->updated($data, null, $modelClass);
        $jsonResponseModel = $apiResponseModel->toResponse(request());
        $responseDataModel = $jsonResponseModel->getData(true);
        expect($responseDataModel['message'])->toBe(__('messages.updated', ['model' => $modelLabel]))
            ->and($responseDataModel['data'])->toBe($data)
            ->and($responseDataModel['metadata'])->toBe([]);

        // Test with data and no model (default message)
        $apiResponseNoModel  = apiResponse()->updated($data);
        $jsonResponseNoModel = $apiResponseNoModel->toResponse(request());
        $responseDataNoModel = $jsonResponseNoModel->getData(true);
        expect($responseDataNoModel['message'])->toBe(__('messages.updated', ['model' => null]))
            ->and($responseDataNoModel['data'])->toBe($data)
            ->and($responseDataNoModel['metadata'])->toBe([]);

        // Test with null data and custom message (no model)
        $apiResponseNullData  = apiResponse()->updated(null, 'Resource successfully modified.');
        $jsonResponseNullData = $apiResponseNullData->toResponse(request());
        $responseDataNullData = $jsonResponseNullData->getData(true);
        expect($responseDataNullData['message'])->toBe('Resource successfully modified.')
            ->and($responseDataNullData['data'])->toBeNull()
            ->and($responseDataNullData['metadata'])->toBe([]);
    });

    it('uses noContentJson method correctly', function (): void {
        $response = apiResponse()->noContentJson();
        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(HttpStatus::HTTP_NO_CONTENT);
    });

    it('uses error method correctly', function (): void {
        // Test with message and default status
        $apiResponse = apiResponse()->error('A generic error occurred');
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_BAD_REQUEST);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe('A generic error occurred')
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with message, custom status, and errors
        $errors             = ['field' => ['Error detail']];
        $apiResponseCustom  = apiResponse()->error('Specific error', HttpStatus::HTTP_NOT_IMPLEMENTED, $errors);
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        expect($jsonResponseCustom->getStatusCode())->toBe(HttpStatus::HTTP_NOT_IMPLEMENTED);
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('Specific error')
            ->and($responseDataCustom['errors'])->toBe($errors)
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('uses validationError method correctly', function (): void {
        // Test with default message
        $apiResponse = apiResponse()->validationError();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_UNPROCESSABLE_ENTITY);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.validation_error'))
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message
        $apiResponseCustom  = apiResponse()->validationError('Your input is not valid.');
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('Your input is not valid.')
            ->and($responseDataCustom['errors'])->toBeNull()
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('uses validationErrors method correctly', function (): void {
        $validationErrors = ['email' => ['The email field is required.']];

        // Test with errors array and default message
        $apiResponse = apiResponse()->validationErrors($validationErrors);
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
        $apiResponseValidator  = apiResponse()->validationErrors($validator, 'Custom validation message');
        $jsonResponseValidator = $apiResponseValidator->toResponse(request());
        $responseDataValidator = $jsonResponseValidator->getData(true);
        expect($responseDataValidator['message'])->toBe('Custom validation message')
            ->and($responseDataValidator['errors'])->toEqual($validator->errors()->toArray())
            ->and($responseDataValidator['metadata'])->toBe([]);
    });

    it('uses notFound method correctly', function (): void {
        $modelClass = App\Models\TestDummyModel::class;
        $modelLabel = get_model_label($modelClass);

        // Test with model and default message
        $apiResponse = apiResponse()->notFound(null, $modelClass);
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_NOT_FOUND);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.not_found', ['model' => $modelLabel]))
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message
        $apiResponseCustom  = apiResponse()->notFound('The item you are looking for does not exist.');
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('The item you are looking for does not exist.')
            ->and($responseDataCustom['errors'])->toBeNull()
            ->and($responseDataCustom['metadata'])->toBe([]);

        // Test without model (generic message)
        $apiResponseGeneric  = apiResponse()->notFound();
        $jsonResponseGeneric = $apiResponseGeneric->toResponse(request());
        $responseDataGeneric = $jsonResponseGeneric->getData(true);
        expect($responseDataGeneric['message'])->toBe(__('messages.resource_not_found'))
            ->and($responseDataGeneric['errors'])->toBeNull()
            ->and($responseDataGeneric['metadata'])->toBe([]);
    });

    it('uses forbidden method correctly', function (): void {
        // Test with default message
        $apiResponse = apiResponse()->forbidden();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_FORBIDDEN);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.forbidden'))
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message and errors
        $errors             = ['permission' => ['Missing required permission']];
        $apiResponseCustom  = apiResponse()->forbidden('You shall not pass!', $errors);
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('You shall not pass!')
            ->and($responseDataCustom['errors'])->toBe($errors)
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('uses unauthorized method correctly', function (): void {
        // Test with default message
        $apiResponse = apiResponse()->unauthorized();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_UNAUTHORIZED);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.unauthenticated'))
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message and errors
        $errors             = ['token' => ['Invalid token']];
        $apiResponseCustom  = apiResponse()->unauthorized('Please log in.', $errors);
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('Please log in.')
            ->and($responseDataCustom['errors'])->toBe($errors)
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('uses methodNotAllowed method correctly', function (): void {
        // Test with default message
        $apiResponse = apiResponse()->methodNotAllowed();
        expect($apiResponse)->toBeInstanceOf(ApiFailResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_METHOD_NOT_ALLOWED);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.method_not_allowed'))
            ->and($responseData['errors'])->toBeNull()
            ->and($responseData['metadata'])->toBe([]);

        // Test with custom message
        $apiResponseCustom  = apiResponse()->methodNotAllowed('This HTTP method is not supported here.');
        $jsonResponseCustom = $apiResponseCustom->toResponse(request());
        $responseDataCustom = $jsonResponseCustom->getData(true);
        expect($responseDataCustom['message'])->toBe('This HTTP method is not supported here.')
            ->and($responseDataCustom['errors'])->toBeNull()
            ->and($responseDataCustom['metadata'])->toBe([]);
    });

    it('uses serverError method correctly', function (): void {
        $originalDebug = config('app.debug'); // Store original debug state

        // Test with default message, app.debug = false
        config(['app.debug' => false]);
        $apiResponse = apiResponse()->serverError();
        expect($apiResponse)->toBeInstanceOf(ApiErrorResponse::class);
        $jsonResponse = $apiResponse->toResponse(request());
        expect($jsonResponse->getStatusCode())->toBe(HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
        $responseData = $jsonResponse->getData(true);
        expect($responseData['message'])->toBe(__('messages.server_error'));
        expect($responseData)->not->toHaveKey('debug');

        // Test with custom message and exception, app.debug = true
        config(['app.debug' => true]);
        $exception     = new RuntimeException('Something broke');
        $apiResponseEx = apiResponse()->serverError('A critical error happened.', $exception);
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
