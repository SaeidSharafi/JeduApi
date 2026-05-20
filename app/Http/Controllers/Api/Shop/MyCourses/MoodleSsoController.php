<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\MyCourses;

use App\Contracts\ApiResponseInterface;
use App\Data\Shop\MyCourses\MoodleSsoUrlData;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\Integrations\MoodleService;
use Throwable;

/**
 * @group Shop - Student Dash - My Courses
 *
 * @authenticated user
 */
final class MoodleSsoController extends Controller
{
    /**
     * Generate a Moodle SSO URL for an enrollment.
     *
     * Returns a live Moodle SSO login URL for the authenticated user's LMS_MOODLE enrollment.
     *
     * @responseFile storage/responses/shop/enrollments/moodle-sso.json
     *
     * @queryParam wantsurl string Optional URL to redirect to after Moodle SSO login. Example: "https://lms.example.com/course/view.php?id=2"
     *
     * @response 404 {"message": "Enrollment not found."}
     * @response 422 {"message": "This enrollment is not a Moodle LMS enrollment."}
     * @response 422 {"message": "Moodle provisioning is incomplete for this enrollment."}
     * @response 422 {"message": "Moodle is not configured."}
     * @response 422 {"message": "Moodle auth_userkey token is not configured."}
     */
    public function __invoke(Enrollment $enrollment, MoodleService $moodleService): ApiResponseInterface
    {
        $user = auth()->user();
        $wantsurl = request()->get('wantsurl');
        if ($enrollment->customer_id !== $user->id) {
            return response()->notFound(__('messages.enrollments.not_found'));
        }

        $deliveryOption = $enrollment->productDeliveryOption;

        if ($deliveryOption->delivery_method !== DeliveryMethodEnum::LMS_MOODLE) {
            return response()->validationError(__('messages.enrollments.not_moodle'));
        }

        $moodleUsername = data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_name');

        if (! is_string($moodleUsername) || $moodleUsername === '') {
            return response()->validationError(__('messages.enrollments.moodle_provisioning_incomplete'));
        }

        $config = Setting::getValue(SettingKeyEnum::MOODLE, config('services.moodle'));

        if (! is_array($config) || ! ($config['enabled'] ?? false) || empty($config['base_url']) || empty($config['token'])) {
            return response()->validationError(__('messages.enrollments.moodle_not_configured'));
        }

        $authUserkeyToken = $config['auth_userkey_token'] ?? null;

        if (! is_string($authUserkeyToken) || $authUserkeyToken === '') {
            return response()->validationError(__('messages.enrollments.moodle_auth_userkey_missing'));
        }

        try {
            $moodleService->setConfig($config);
            $url = $moodleService->createUserKey($moodleUsername, $authUserkeyToken);
        } catch (Throwable $e) {
            report($e);

            return response()->validationError(__('messages.enrollments.moodle_service_error'));
        }

        return response()->success(new MoodleSsoUrlData(url: $url, wantsurl: $wantsurl));
    }
}
