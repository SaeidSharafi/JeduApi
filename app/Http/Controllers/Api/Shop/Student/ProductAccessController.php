<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\Student\LookupProductAccessAction;
use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\ProductAccessData;
use App\Data\Shop\Student\ProductAccessLookupRequestData;
use App\Http\Controllers\Controller;
use App\Models\User;

/**
 * @group Shop - Student - Product Access
 *
 * @authenticated user
 */
final class ProductAccessController extends Controller
{
    /**
     * Look up the authenticated customer's existing access for selected product options.
     *
     * Only course, seminar, and digital asset access is returned. Enrollments created for Bundle components
     * are included when their exact component PDO UUID is requested; Bundle PDOs have no standalone enrollment.
     *
     * @responseFile 200 resources/responses/shop/student/product-access.json
     */
    public function __invoke(
        ProductAccessLookupRequestData $requestData,
        LookupProductAccessAction $action,
    ): ApiResponseInterface {
        /** @var User $user */
        $user = auth()->user();

        return apiResponse()->success(ProductAccessData::collect(
            $action->handle($user, $requestData->option_uuids),
        ));
    }
}
