<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Forms;

use App\Actions\Shop\Forms\CreateCollaborationRequestAction;
use App\Data\Shop\Forms\CreateCollaborationRequestData;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Forms
 *
 * API for handling form submissions
 */
final class CollaborationRequestController extends Controller
{
    /**
     * Submit Collaboration Request
     *
     * Handles the submission of a collaboration request form.
     *
     * @responseFile 201 resources/responses/shop/forms/collaboration-request.json
     * @responseFile 429 resources/responses/422.json
     */
    public function __invoke(CreateCollaborationRequestData $data, CreateCollaborationRequestAction $action): \App\Contracts\ApiResponseInterface
    {
        $action->handle($data);

        return apiResponse()->created(null, __('shop.responses.forms.collaboration_request_submitted'));
    }
}
