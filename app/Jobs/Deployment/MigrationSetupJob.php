<?php

declare(strict_types=1);

namespace App\Jobs\Deployment;

use Illuminate\Support\Facades\Log;

final class MigrationSetupJob extends BaseDeploymentJob
{
    public function __construct(protected string $projectPath) {}

    public function handle(): void
    {
        Log::channel('deployment')->info('🚀 Starting ArtisanCommandsJob...');
        $phpExecutable = '/usr/bin/php8.4'; // Or from config
        $artisanScript = "{$phpExecutable} {$this->projectPath}/artisan";

        $commandsToRun = ["{$artisanScript} optimize:clear"];
        if (! app()->isProduction()) {
            $commandsToRun[] = "{$artisanScript} migrate:fresh";
        }
        $commandsToRun[] = "{$artisanScript} permissions:sync --guard=staff";
        $commandsToRun[] = "{$artisanScript} permissions:sync --guard=user";
        $commandsToRun[] = "{$artisanScript} optimize";

        foreach ($commandsToRun as $command) {
            Log::channel('deployment')->info("Artisan: Running '{$command}'...");
            $this->runProcess($command, $this->projectPath, 180);
        }

        Log::channel('deployment')->info('✅ MigrationSetupJob completed.');
    }
}
