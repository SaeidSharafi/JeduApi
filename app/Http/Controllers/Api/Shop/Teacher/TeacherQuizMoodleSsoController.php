<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Actions\Shop\GenerateQuizMoodleSsoUrlAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @group Shop - Teacher - Quizzes
 *
 * @authenticated user
 */
final class TeacherQuizMoodleSsoController extends Controller
{
    /**
     * Generate a Moodle SSO URL for a quiz.
     *
     * @urlParam courseModuleId integer required The Moodle course-module ID (`cmid`), taken from the quiz activity's `cid` field in the quiz list response. Example: 841
     */
    public function __invoke(Request $request, int $courseModuleId, GenerateQuizMoodleSsoUrlAction $generateSsoUrl): ApiResponseInterface
    {
        abort_unless((bool) $request->user()?->teacherData, 403);

        $ssoData = $generateSsoUrl->forTeacher($request->user(), $courseModuleId);

        return $ssoData
            ? apiResponse()->success($ssoData)
            : apiResponse()->validationError(__('messages.enrollments.moodle_service_error'));
    }
}
