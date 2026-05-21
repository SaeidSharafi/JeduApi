<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\Integrations\MoodleProgressRefresher;
use App\Services\Integrations\MoodleService;
use Exception;
use SmartCache\Facades\SmartCache;

/**
 * @group Shop - Student Dash - My Courses
 *
 * @authenticated user
 */
final class UpdateMoodleProgressController extends Controller
{
    /**
     * Update Moodle course structure & progress for an enrollment.
     *
     * @responseFile storage/responses/shop/enrollments/moodle-progress.json
     *
     * @response 404 {"message": "Enrollment not found."}
     * @response 422 {"message": "This enrollment is not a Moodle LMS enrollment."}
     * @response 422 {"message": "Moodle provisioning is incomplete for this enrollment."}
     * @response 422 {"message": "Moodle is not configured."}
     * @response 422 {"message": "Moodle auth_userkey token is not configured."}
     */
    public function __invoke(Enrollment $enrollment, MoodleService $moodleService)
    {
        $user = auth()->user();

        if ($enrollment->customer_id !== $user->id) {
            return response()->notFound(__('messages.enrollments.not_found'));
        }

        $deliveryOption = $enrollment->productDeliveryOption;
        if ($deliveryOption->delivery_method !== DeliveryMethodEnum::LMS_MOODLE) {
            return response()->validationError(__('messages.enrollments.not_moodle'));
        }

        try {
            $config = Setting::getValue(SettingKeyEnum::MOODLE, config('services.moodle'));
            $moodleService->setConfig($config);

            $moodleCourseId = data_get($enrollment->productDeliveryOption->details_json, 'moodle_course_id');
            $moodleUserId   = data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_id');

            if (! $moodleCourseId || ! $moodleUserId) {
                return response()->validationError(__('messages.enrollments.moodle_provisioning_incomplete'));
            }

            $cacheKey = "moodle-dashboard.{$enrollment->id}.{$moodleCourseId}.{$moodleUserId}";

            $responseArray = SmartCache::asyncSwr(
                $cacheKey,
                callback: new MoodleProgressRefresher($enrollment->id, $moodleCourseId, $moodleUserId),
                ttl: 300,
                staleTtl: 900
            );

            return response()->success($responseArray);

        } catch (Exception $exception) {
            report($exception);

            return response()->error(__('messages.something_went_wrong'));
        }
    }
}
