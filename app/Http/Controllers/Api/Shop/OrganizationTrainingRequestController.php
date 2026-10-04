<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\Organization\CreateOrganizationTrainingRequestAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Organization\OrganizationTrainingRequestCreateData;
use App\Data\Shop\Organization\OrganizationTrainingRequestSubmittedData;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Organization
 *
 * Public Organization training request intake.
 */
final class OrganizationTrainingRequestController extends Controller
{
    /**
     * Submit an Organization training request.
     *
     * @responseFile 201 resources/responses/shop/organization/training-request.json
     * @responseFile 422 resources/responses/422.json
     */
    public function __invoke(
        OrganizationTrainingRequestCreateData $data,
        CreateOrganizationTrainingRequestAction $action,
    ): ApiResponseInterface {
        $request = $action->handle($data);

        return apiResponse()->created(
            OrganizationTrainingRequestSubmittedData::fromModel($request),
            __('shop.responses.forms.organization_training_request_submitted'),
        );
    }
}
