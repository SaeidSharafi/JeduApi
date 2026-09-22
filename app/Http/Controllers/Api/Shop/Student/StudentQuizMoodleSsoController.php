<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Actions\Shop\GenerateQuizMoodleSsoUrlAction;
use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @group Shop - Student - Quizzes
 *
 * @authenticated user
 */
final class StudentQuizMoodleSsoController extends Controller
{
    public function __invoke(Request $request, int $courseModuleId, GenerateQuizMoodleSsoUrlAction $generateSsoUrl): ApiResponseInterface
    {
        $ssoData = $generateSsoUrl->forStudent($request->user(), $courseModuleId);

        return $ssoData
            ? apiResponse()->success($ssoData)
            : apiResponse()->validationError(__('messages.enrollments.moodle_service_error'));
    }
}
