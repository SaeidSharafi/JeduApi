<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\Student\MoodleSsoUrlData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use App\Services\Integrations\MoodleService;
use App\Services\SettingsService;
use Exception;
use Illuminate\Support\Facades\Auth;

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
     *
     * @responseFile resources/responses/shop/enrollments/moodle-sso.json
     *
     * @queryParam wantsurl string Optional URL to redirect to after Moodle SSO login. Example: "https://lms.example.com/course/view.php?id=2"
     *
     * @response 403 {"message": "Access Denied"}
     * @response 422 {"message": "This course is not a Moodle course."}
     * @response 422 {"message": "Moodle is not configured."}
     * @response 422 {"message": "Moodle auth_userkey token is not configured."}
     */
    public function __invoke(ProductDeliveryOption $deliveryOption, MoodleService $moodleService, SettingsService $settings): ApiResponseInterface
    {
        /** @var Teacher|null $teacher */
        $teacher = Auth::user()?->teacherData;
        abort_unless(!!$teacher, 403);

        $wantsurl = request()->get('wantsurl');
        $teacherOwn = $deliveryOption->teachers()->where('teacher_id', $teacher->id)->exists();
        abort_unless($teacherOwn, 403);

        if ($deliveryOption->delivery_method !== DeliveryMethodEnum::LMS_MOODLE) {
            return apiResponse()->validationError(__('messages.enrollments.not_moodle'));
        }

        $moodleUsername = Auth::user()->civil_id;

        try {
            $url = $moodleService->createUserKey($moodleUsername);
        } catch (Exception $e) {
            report($e);

            return apiResponse()->validationError(__('messages.enrollments.moodle_service_error'));
        }

        return apiResponse()->success(new MoodleSsoUrlData(url: $url, wantsurl: $wantsurl));
    }
}
