<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Integrations\MoodleClientContract;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;

/**
 * @group Admin - Moodle
 *
 * @authenticated user
 */
final class MoodleSsoController extends Controller
{
    /**
     * Generate a Moodle SSO URL for a staff/admin member.
     *
     * Returns a live Moodle SSO login URL for the authenticated staff user.
     *
     * @responseFile resources/responses/shop/enrollments/moodle-sso.json
     *
     * @queryParam wantsurl string Optional URL to redirect to after Moodle SSO login. Example:
     *     "https://lms.example.com/admin"
     *
     * @response 401 {"message": "Unauthenticated."}
     * @response 422 {"message": "Moodle username not found for staff member."}
     * @response 422 {"message": "Moodle service error."}
     */
    public function __invoke(Request $request, MoodleClientContract $moodleService): ApiResponseInterface
    {
        /** @var Staff|null $staff */
        $staff = auth('staff')->user();

        if (! $staff) {
            return apiResponse()->unauthorized();
        }

        if (empty($staff->email)) {
            return apiResponse()->validationError(__('messages.enrollments.moodle_service_error'));
        }

        $ssoData = $moodleService->generateSsoUrl(
            username: $staff->email,
            wantsUrl: $request->query('wantsurl')
        );

        if (! $ssoData) {
            return apiResponse()->validationError(__('messages.enrollments.moodle_service_error'));
        }

        return apiResponse()->success($ssoData);
    }
}
