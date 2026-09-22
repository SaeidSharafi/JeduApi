<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Contracts\Integrations\MoodleClientContract;
use App\Data\Shop\Student\MoodleSsoUrlData;
use App\Models\User;

final class GenerateQuizMoodleSsoUrlAction
{
    public function __construct(
        private readonly MoodleClientContract $moodle,
        private readonly GenerateMoodleSsoUrlAction $generateSsoUrl,
    ) {}

    public function forStudent(User $user, int $courseModuleId): ?MoodleSsoUrlData
    {
        return $this->handle($user, $courseModuleId, false);
    }

    public function forTeacher(User $user, int $courseModuleId): ?MoodleSsoUrlData
    {
        return $this->handle($user, $courseModuleId, true);
    }

    private function handle(User $user, int $courseModuleId, bool $asTeacher): ?MoodleSsoUrlData
    {
        [$moodleUserId, $username] = $this->moodle->findOrCreateUser($user);

        abort_unless($this->moodle->canAccessQuiz($moodleUserId, $courseModuleId, $asTeacher), 404);

        return $this->generateSsoUrl->handle($username, "/mod/quiz/view.php?id={$courseModuleId}");
    }
}
