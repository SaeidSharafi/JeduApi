<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Forms;

use App\Actions\Shop\CMS\StoreContactUsRequestAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Forms\ContactUsRequestData;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Contact
 *
 * Public Contact Us form submission
 */
final class ContactUsRequestController extends Controller
{
    /**
     * Submit Contact Us form
     *
     * Accepts and stores a public contact request.
     *
     * @responseFile storage/responses/shop/contactus/show.json
     */
    public function __invoke(ContactUsRequestData $data, StoreContactUsRequestAction $action): ApiResponseInterface
    {
        $contactRequest = $action->handle($data);
        return response()->success($contactRequest);
    }
}
