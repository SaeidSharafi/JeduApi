<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Actions\Shop\GenerateMoodleSsoUrlAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\ProductableEnum;
use App\Exceptions\BundleStructuralInvariantException;
use App\Http\Controllers\Controller;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use Illuminate\Http\Request;

/**
 * @group Shop - Teacher - Courses
 *
 * @authenticated user
 */
final class TeacherCourseMoodleSsoController extends Controller
{
    /**
     * Generate a Moodle SSO URL for teacher.
     *
     * Returns a live Moodle SSO login URL for the authenticated teacher.
     *
     * @responseFile resources/responses/shop/enrollments/moodle-sso.json
     *
     * @response 403 {"message": "Access Denied"}
     * @response 422 {"message": "This course is not a Moodle course."}
     * @response 422 {"message": "Moodle provisioning is incomplete for this enrollment."}
     * @response 422 {"message": "Moodle service error."}
     */
    public function __invoke(
        Request $request,
        ProductDeliveryOption $deliveryOption,
        GenerateMoodleSsoUrlAction $generateSsoUrl
    ): ApiResponseInterface {
        $user = $request->user();

        /** @var Teacher|null $teacher */
        $teacher = $user?->teacherData;
        abort_unless((bool) $teacher, 403);

        $teacherOwnsOption = $deliveryOption->teachers()
            ->where('teacher_id', $teacher->id)
            ->exists();

        abort_unless($teacherOwnsOption, 403);

        if ($deliveryOption->product?->productable_type === ProductableEnum::BUNDLE->value) {
            throw new BundleStructuralInvariantException();
        }

        if ($deliveryOption->delivery_method !== DeliveryMethodEnum::LMS_MOODLE) {
            return apiResponse()->validationError(__('messages.enrollments.not_moodle'));
        }

        $moodleUsername = $user->civil_id;

        if (empty($moodleUsername) || ! is_string($moodleUsername)) {
            return apiResponse()->validationError(__('messages.enrollments.moodle_provisioning_incomplete'));
        }

        $courseId = data_get($deliveryOption->details_json, 'moodle_course_id');
        $courseId = filter_var($courseId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($courseId === false) {
            return apiResponse()->validationError(__('messages.enrollments.moodle_provisioning_incomplete'));
        }

        $ssoData = $generateSsoUrl->handle($moodleUsername, '/course/view.php?id='.$courseId);

        if (! $ssoData) {
            return apiResponse()->validationError(__('messages.enrollments.moodle_service_error'));
        }

        return apiResponse()->success($ssoData);
    }
}
