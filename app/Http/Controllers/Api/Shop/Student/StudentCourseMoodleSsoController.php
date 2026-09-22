<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\GenerateMoodleSsoUrlAction;
use App\Contracts\ApiResponseInterface;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\Request;

/**
 * @group Shop - Student - Courses
 *
 * @authenticated user
 */
final class StudentCourseMoodleSsoController extends Controller
{
    /**
     * Generate a Moodle SSO URL for an enrollment.
     *
     * Returns a live Moodle SSO login URL for the authenticated user's LMS_MOODLE enrollment.
     *
     * @responseFile resources/responses/shop/enrollments/moodle-sso.json
     *
     * @response 404 {"message": "Enrollment not found."}
     * @response 422 {"message": "This enrollment is not a Moodle LMS enrollment."}
     * @response 422 {"message": "Moodle provisioning is incomplete for this enrollment."}
     * @response 422 {"message": "Moodle is not configured."}
     * @response 422 {"message": "Moodle auth_userkey token is not configured."}
     */
    public function __invoke(Request $request, Enrollment $enrollment, GenerateMoodleSsoUrlAction $generateSsoUrl): ApiResponseInterface
    {
        if ($enrollment->customer_id !== $request->user()?->id) {
            return apiResponse()->notFound(__('messages.enrollments.not_found'));
        }

        // 2. Delivery method check
        if ($enrollment->productDeliveryOption?->delivery_method !== DeliveryMethodEnum::LMS_MOODLE) {
            return apiResponse()->validationError(__('messages.enrollments.not_moodle'));
        }

        // 3. Provisioning check
        $moodleUsername = data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_username')
            ?? data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_name');

        if (! is_string($moodleUsername) || $moodleUsername === '') {
            return apiResponse()->validationError(__('messages.enrollments.moodle_provisioning_incomplete'));
        }

        $courseId = data_get($enrollment->productDeliveryOption->details_json, 'moodle_course_id');
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
