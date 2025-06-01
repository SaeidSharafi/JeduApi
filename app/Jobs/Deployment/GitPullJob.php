<?php

namespace App\Jobs\Deployment;

use Illuminate\Support\Facades\Log;

class GitPullJob extends BaseDeploymentJob
{
    public int $timeout = 120;
    public function __construct(protected string $projectPath, protected string $remoteUrlWithCreds, protected string $expectedRepo)
    {
    }

    public function handle(): void
    {
        Log::channel('deployment')->info('🚀 Starting GitPullJob...');

        // Git Reset (if production) - Simplified from your command
        if (app()->isProduction()) {
            Log::channel('deployment')->info('Git: Resetting local changes...');
            $this->runProcess('git reset --hard HEAD', $this->projectPath);
        }

        Log::channel('deployment')->info("Git: Pulling 'main' branch from {$this->expectedRepo}...");
        $pullCommand = "git pull {$this->remoteUrlWithCreds} main";
        $this->runProcess($pullCommand, $this->projectPath);

        Log::channel('deployment')->info('✅ GitPullJob completed.');
    }
}
