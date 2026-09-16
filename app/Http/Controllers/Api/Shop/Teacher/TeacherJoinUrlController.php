<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Actions\Shop\Teacher\GetTeacherJoinUrlAction;
use App\Contracts\ApiResponseInterface;
use App\Exceptions\Integrations\ResourceNotProvisionedException;
use App\Http\Controllers\Controller;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * @group Shop - Teacher - Courses
 *
 * @authenticated user
 */
final class TeacherJoinUrlController extends Controller
{
    /**
     * Get the session login URL for a teacher's live seminar.
     *
     * Returns a short-lived provider login URL that drops the authenticated teacher into their own
     * seminar room. Skyroom seminars return a presenter login URL; no provider user is created.
     *
     * @responseFile resources/responses/shop/teacher/join.json
     * @responseFile 403 resources/responses/403.json
     *
     * @response 422 {"message": "This is not an online seminar (live class)."}
     * @response 422 {"message": "Delivery method [live_session_bbb] does not support join URLs."}
     * @response 503 {"message": "Skyroom room_id is missing from delivery option details."}
     */
    public function __invoke(
        Request $request,
        ProductDeliveryOption $deliveryOption,
        GetTeacherJoinUrlAction $action,
    ): ApiResponseInterface {
        $user = $request->user();

        /** @var Teacher|null $teacher */
        $teacher = $user?->teacherData;
        abort_unless((bool) $teacher, 403);

        abort_unless(
            $deliveryOption->teachers()->where('teacher_id', $teacher->id)->exists(),
            403,
        );

        try {
            $joinUrlData = $action->handle($user, $deliveryOption);
        } catch (ResourceNotProvisionedException $e) {
            return apiResponse()->error($e->getMessage(), 503);
        } catch (InvalidArgumentException $e) {
            return apiResponse()->validationError($e->getMessage());
        }

        return apiResponse()->success($joinUrlData);
    }
}
