<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Contracts\ApiResponseInterface;
use App\Http\Controllers\Controller;
use App\Services\Integrations\MoodleService;
use App\Services\SWRCacheService;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Teacher - Quizzes
 *
 * @authenticated user
 */
final class QuizController extends Controller
{
    /**
     * List of Quizzes
     *
     * Return list of Quizzes on Moodle with Teacher Access for the authenticated user.
     * @responseFile 200 resources/responses/shop/student/quizzes.json
     */
    public function __invoke(MoodleService $moodleService): ApiResponseInterface
    {
        $user    = Auth::user();
        abort_unless(Auth::user()?->is_teacher, 403);
        $quizzes = SWRCacheService::remember('teacher_quizzes:'.$user->id, function () use ($moodleService, $user): array {
            [$moodleUserId] = $moodleService->findOrCreateUser($user);
            $quizzes        = $moodleService->getTeacherQuizzes($moodleUserId);

            return $quizzes;
        });

        return apiResponse()->success($quizzes);
    }
}
