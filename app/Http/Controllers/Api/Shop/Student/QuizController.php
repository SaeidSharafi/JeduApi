<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Shop\Student;

use App\Contracts\Cache\CacheStore;
use App\Contracts\Integrations\MoodleClientContract;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Controller;

/**
 * @group Shop - Student - Quizzes
 *
 * @authenticated user
 */
final class QuizController extends Controller
{
    public function __construct(private readonly CacheStore $cache) {}

    /**
     * Return List of Quizzes (on Moodle) for the authenticated user.
     *
     * @responseFile 200 resources/responses/shop/student/quizzes.json
     */
    public function __invoke(MoodleClientContract $moodleService): \App\Contracts\ApiResponseInterface
    {
        $user = auth()->user();

        $quizzes = $this->cache->flexible(
            CacheKey::StudentQuizzes,
            ['userId' => $user->id],
            function () use ($moodleService, $user): array {
                [$moodleUserId] = $moodleService->findOrCreateUser($user);

                return $moodleService->getAllQuizzes($moodleUserId);
            });

        return apiResponse()->success($quizzes);
    }
}
