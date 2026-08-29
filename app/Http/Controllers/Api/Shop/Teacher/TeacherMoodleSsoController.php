<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Integrations\MoodleClientContract;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use Illuminate\Http\Request;

/**
 * @group Shop - Teacher - Courses
 *
 * @authenticated user
 */
final class TeacherMoodleSsoController extends Controller
{
    /**
     * Generate a Moodle SSO URL for teacher.
     *
     * Returns a live Moodle SSO login URL for the authenticated teacher.
     * If wantsurl is omitted, it defaults to the Moodle course page.
     *
     * @responseFile resources/responses/shop/enrollments/moodle-sso.json
     *
     * @queryParam wantsurl string Optional URL to redirect to after Moodle SSO login. Example:
     *     "https://lms.example.com/course/view.php?id=2"
     *
     * @response 403 {"message": "Access Denied"}
     * @response 422 {"message": "This course is not a Moodle course."}
     * @response 422 {"message": "Moodle provisioning is incomplete for this enrollment."}
     * @response 422 {"message": "Moodle service error."}
     */
    public function __invoke(
        Request $request,
        ProductDeliveryOption $deliveryOption,
        MoodleClientContract $moodleService
    ): ApiResponseInterface {
        $user = $request->user();

        /** @var Teacher|null $teacher */
        $teacher = $user?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherOwnsOption = $deliveryOption->teachers()
            ->where('teacher_id', $teacher->id)
            ->exists();

        abort_unless($teacherOwnsOption, 403);

        if ($deliveryOption->delivery_method !== DeliveryMethodEnum::LMS_MOODLE) {
            return apiResponse()->validationError(__('messages.enrollments.not_moodle'));
        }

        $moodleUsername = $user->civil_id;

        if (empty($moodleUsername) || ! is_string($moodleUsername)) {
            return apiResponse()->validationError(__('messages.enrollments.moodle_provisioning_incomplete'));
        }

        // Fallback to course page if wantsurl is omitted
        $courseId = data_get($deliveryOption->details_json, 'moodle_course_id');
        $wantsUrl = $request->query('wantsurl') ?? ($courseId ? "/course/view.php?id={$courseId}" : null);

        // Generate SSO URL using the service
        $ssoData = $moodleService->generateSsoUrl($moodleUsername, $wantsUrl);

        if (! $ssoData) {
            return apiResponse()->validationError(__('messages.enrollments.moodle_service_error'));
        }

        return apiResponse()->success($ssoData);
    }
}
