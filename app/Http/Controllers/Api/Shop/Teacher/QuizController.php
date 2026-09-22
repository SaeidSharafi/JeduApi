<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Teacher;

use App\Contracts\ApiResponseInterface;
use App\Contracts\Cache\CacheStore;
use App\Contracts\Integrations\MoodleClientContract;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * @group Shop - Teacher - Quizzes
 *
 * @authenticated user
 */
final class QuizController extends Controller
{
    public function __construct(private readonly CacheStore $cache) {}

    /**
     * List of Quizzes
     *
     * Return list of Quizzes on Moodle with Teacher Access for the authenticated user.
     *
     * For each quiz activity, `cid` is Moodle's course-module ID (cmid). Pass that
     * value as `courseModuleId` to the quiz Moodle SSO endpoint.
     *
     * @responseFile 200 resources/responses/shop/student/quizzes.json
     */
    public function __invoke(MoodleClientContract $moodleService): ApiResponseInterface
    {
        $user = Auth::user();
        abort_unless(Auth::user()?->is_teacher, 403);

        $quizzes = $this->cache->flexible(
            CacheKey::TeacherQuizzes,
            ['userId' => $user->id],
            function () use ($moodleService, $user): array {
                [$moodleUserId] = $moodleService->findOrCreateUser($user);

                return $moodleService->getTeacherQuizzes($moodleUserId);
            });

        return apiResponse()->success($quizzes);
    }
}
