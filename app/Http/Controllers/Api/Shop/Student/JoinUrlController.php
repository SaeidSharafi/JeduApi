<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\Student\GetJoinUrlAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\Integrations\ResourceNotProvisionedException;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Exception;
use InvalidArgumentException;

/**
 * @group Shop - Student - Courses
 *
 * @subgroup Join URL
 *
 * @authenticated user
 */
final class JoinUrlController extends Controller
{
    /**
     * Get join URL for a live session enrollment.
     *
     * Returns a time-limited join URL for BBB or Skyroom live sessions.
     *
     * @responseFile resources/responses/shop/my-courses/join.json
     *
     * @response 404 {"message": "Enrollment not found."}
     * @response 422 {"message": "Delivery method does not support join URLs."}
     * @response 503 {"message": "BBB meeting not provisioned yet."}
     */
    public function __invoke(Enrollment $enrollment, GetJoinUrlAction $action): ApiResponseInterface
    {
        if ($enrollment->customer_id !== auth()->id()) {
            return apiResponse()->notFound();
        }

        try {
            $joinUrlData = $action->handle($enrollment);
        } catch (ResourceNotProvisionedException $e) {
            return apiResponse()->error($e->getMessage(), 503);
        } catch (InvalidArgumentException $e) {
            return apiResponse()->validationError($e->getMessage());
        } catch (Exception $e) {
            report($e);

            return apiResponse()->serverError();
        }

        return apiResponse()->success($joinUrlData);
    }
}
