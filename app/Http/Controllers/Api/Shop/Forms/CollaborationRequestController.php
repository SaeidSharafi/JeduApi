<?php

namespace App\Http\Controllers\Api\Shop\Forms;

use App\Actions\Shop\Forms\CreateCollaborationRequestAction;
use App\Data\Shop\Forms\CreateCollaborationRequestData;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Forms
 *
 * API for handling form submissions
 */
class CollaborationRequestController extends Controller
{

    /**
     * Submit Collaboration Request
     *
     * Handles the submission of a collaboration request form.
     *
     * @response 200 {
     *          "message": "Your message has been successfully sent. We will get back to you as soon as possible.",
     *          "data": null,
     *          "metadata": []
     * }
     * @response 429 {
     * "message": "Too Many Attempts.",
     * "errors": null,
     * "metadata": []
     * }
     */
    public function __invoke(CreateCollaborationRequestData $data, CreateCollaborationRequestAction $action)
    {
        $action->handle($data);
        return response()->created(null, __('shop.responses.forms.collaboration_request_submitted') );
    }
}
