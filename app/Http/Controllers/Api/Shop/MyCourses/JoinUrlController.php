<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Actions\Shop\MyCourses\GetJoinUrlAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use InvalidArgumentException;
use Throwable;

/**
 * @group Shop - Student Dash - My Courses
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
            return response()->notFound();
        }

        try {
            $joinUrlData = $action->handle($enrollment);
        } catch (ExternalProvisioningException $e) {
            return response()->error($e->getMessage(), 503);
        } catch (InvalidArgumentException $e) {
            return response()->validationError($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return response()->serverError();
        }

        return response()->success($joinUrlData);
    }
}
