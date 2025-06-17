<?php

declare(strict_types=1);

namespace App\Jobs\Deployment;

use Illuminate\Support\Facades\Log;

final class DemoSeedJob extends BaseDeploymentJob
{
    public function __construct(protected string $projectPath) {}

    public function handle(): void
    {
        Log::channel('deployment')->info('🚀 Starting DemoSeedJob...');
        $phpExecutable = '/usr/bin/php8.4'; // Or from config
        $artisanScript = "{$phpExecutable} {$this->projectPath}/artisan";

        if (app()->isProduction()) {
            Log::channel('deployment')->info('In production, skipping Demo seeding...');

            return;
        }
        $command = "{$artisanScript} db:seed --class=ScribeSeeder";
        Log::channel('deployment')->info("Artisan: Running '{$command}'...");
        $this->runProcess($command, $this->projectPath, 180);

        Log::channel('deployment')->info('✅ DemoSeedJob completed.');
    }
}
