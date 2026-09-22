<?php

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Contracts\Integrations\MoodleClientContract;
use App\Data\Shop\Student\MoodleSsoUrlData;

final class GenerateMoodleSsoUrlAction
{
    public function __construct(private readonly MoodleClientContract $moodle) {}

    public function handle(string $username, string $destination): ?MoodleSsoUrlData
    {
        return $this->moodle->generateSsoUrl($username, $destination);
    }
}
