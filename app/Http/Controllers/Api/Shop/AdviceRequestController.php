<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop;

use App\Actions\Shop\Forms\StoreAdviceRequestAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Forms\AdviceRequestCreateData;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Forms
 *
 * Public advice request form submission
 */
final class AdviceRequestController extends Controller
{
    /**
     * Request Consultation
     *
     * Submits a user's phone number to request a private consultation.
     *
     * @responseFile resources/responses/shop/advice-request/show.json
     */
    public function __invoke(AdviceRequestCreateData $data, StoreAdviceRequestAction $action): ApiResponseInterface
    {
        $action->handle($data);

        return apiResponse()->created(null, __('shop.responses.advice_request_submitted'));
    }
}
