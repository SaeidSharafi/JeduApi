<?php

namespace App\Http\Controllers\Api\Shop\Student;

use App\Http\Controllers\Controller;
use App\Services\Integrations\MoodleService;
use App\Services\SWRCacheService;

/**
 * @group Shop - Student - Quizzes
 *
 * @authenticated user
 */
class QuizController extends Controller
{
    /**
     * Return List of Quizzes (on Moodle) for the authenticated user.
     *
     * @responseFile 200 resources/responses/shop/student/quizzes.json
     */
    public function __invoke(MoodleService $moodleService)
    {
        $user = auth()->user();
       $quizzes = SWRCacheService::remember("student_quizzes", function () use ($moodleService, $user) {
            [$moodleUserId] = $moodleService->findOrCreateUser($user);
            $quizzes = $moodleService->getAllQuizzes($moodleUserId);
            return $quizzes;
        });
        return response()->success($quizzes);
    }
}
