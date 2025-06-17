<?php

declare(strict_types=1);

namespace App\Jobs\Deployment;

use Illuminate\Support\Facades\Log;

final class ComposerInstallJob extends BaseDeploymentJob
{
    public int $timeout = 180;

    public function __construct(protected string $projectPath) {}

    public function handle(): void
    {
        Log::channel('deployment')->info('🚀 Starting ComposerInstallJob...');

        if (app()->environment() !== 'local') {
            $noDev           = app()->isProduction() ? '--no-dev' : '';
            $phpExecutable   = '/usr/bin/php8.4'; // Or from config
            $composerCommand = "{$phpExecutable} /usr/local/bin/composer install --no-interaction {$noDev} --prefer-dist --optimize-autoloader";
            $this->runProcess($composerCommand, $this->projectPath);
        }

        Log::channel('deployment')->info('✅ ComposerInstallJob completed.');
    }
}
