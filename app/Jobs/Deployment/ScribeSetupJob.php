<?php

declare(strict_types=1);

namespace App\Jobs\Deployment;

use Illuminate\Support\Facades\Log;

final class ScribeSetupJob extends BaseDeploymentJob
{
    public function __construct(protected string $projectPath) {}

    public function handle(): void
    {
        Log::channel('deployment')->info('🚀 Starting ScribeSetupJob...');
        $phpExecutable = '/usr/bin/php8.4'; // Or from config
        $artisanScript = "{$phpExecutable} {$this->projectPath}/artisan";

        if (app()->isProduction()) {
            Log::channel('deployment')->info('In production, skipping Scribe setup...');

            return;
        }
        $command = "{$artisanScript} scribe:setup --fresh --seed";
        Log::channel('deployment')->info("Artisan: Running '{$command}'...");
        $this->runProcess($command, $this->projectPath, 180);

        Log::channel('deployment')->info('✅ ScribeSetupJob completed.');
    }
}
