<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Webhooks;

use App\Actions\Shop\Student\CompleteEnrollmentSurveyAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Webhooks\PorslineSurveySubmissionData;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Webhooks
 *
 * @unauthenticated
 */
final class PorslineSurveyWebhookController extends Controller
{
    /**
     * Accept a verified Porsline survey submission.
     *
     * Send the static `X-Survey-Webhook-Secret` header configured for this deployment.
     * The documented header value is a placeholder; Porsline must use the configured deployment value.
     *
     * @header X-Survey-Webhook-Secret configured-secret
     *
     * @responseFile 200 resources/responses/shop/surveys/show.json
     */
    public function __invoke(
        PorslineSurveySubmissionData $submission,
        CompleteEnrollmentSurveyAction $action,
    ): ApiResponseInterface {
        $action->handle($submission);

        return apiResponse()->success();
    }
}
