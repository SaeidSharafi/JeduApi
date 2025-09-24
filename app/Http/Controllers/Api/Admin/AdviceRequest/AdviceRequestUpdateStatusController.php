<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\AdviceRequest;

use App\Actions\Admin\AdviceRequest\UpdateAdviceRequestStatusAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Admin\AdviceRequest\AdviceRequestData;
use App\Data\Admin\AdviceRequest\AdviceRequestUpdateData;
use App\Http\Controllers\Controller;
use App\Models\AdviceRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin - Advice Requests
 *
 * @authenticated
 * APIs for managing advice requests
 */
final class AdviceRequestUpdateStatusController extends Controller
{
    /**
     * Update Advice Request Status
     *
     * Update the status of a specific advice request.
     *
     * @bodyParam status string required The new status of the advice request. Example: handled
     *
     * @responseFile 200 responses/advice-request/show.json
     *
     * @response 403 responses/403.json
     * @response 404 responses/404.json
     */
    public function __invoke(
        AdviceRequestUpdateData $data,
        AdviceRequest $adviceRequest,
        UpdateAdviceRequestStatusAction $action
    ): ApiResponseInterface {
        Gate::authorize('update', $adviceRequest);

        $adviceRequest = $action->handle($data, $adviceRequest, auth('staff')->user());
        $adviceRequest->load('handler');

        return response()->updated(AdviceRequestData::from($adviceRequest), model: AdviceRequest::class);
    }
}
