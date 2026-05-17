<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

final class DeployApplicationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deploy-application 
    {--hamgit : Use Hamgit repo}
    {--skip-dep : Skip installing dependencies}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploys the application: pulls from git, installs dependencies, and runs migrations/optimizations.';

    /**
     * Execute the console command.
     */
    public function handle(): int // Return int for success/failure
    {
        $this->info('🚀 Starting application deployment...');
        Log::channel('deployment')->info('🚀 Deployment initiated via Artisan command...');

        $projectPath   = base_path();
        $gitUsername   = config('app.git_deploy_username', config('app.git_deploy_username'));
        $gitPat        = config('app.git_deploy_pat', config('app.git_deploy_pat'));
        $expectedRepo  = 'SaeidSharafi/JeduApi'; // Your GitHub Owner/Repo
        $phpExecutable = '/usr/bin/php8.4'; // Or your PHP path from config
        $artisanScript = "{$phpExecutable} {$projectPath}/artisan"; // Use a variable
        $useHamgit     = $this->option('hamgit');
        if (! $useHamgit && (empty($gitUsername) || empty($gitPat))) {
            $this->error('✘ Git username or PAT not configured in .env (GIT_DEPLOY_USERNAME, GIT_DEPLOY_PAT).');
            Log::channel('deployment')->error('✘ Git username or PAT not configured.');

            return Command::FAILURE;
        }

        // Construct the remote URL with credentials
        $remoteUrlWithCreds = $this->option('hamgit')
            ? 'hamgit'
            : "https://{$gitUsername}:{$gitPat}@github.com/{$expectedRepo}.git";

        try {
            // --- Git Operations ---
            if (app()->isProduction()) {
                $this->line('Git: Resetting local changes...');
                Log::channel('deployment')->info('Git: Resetting local changes...');
                if (! $this->runProcess('git reset --hard HEAD', $projectPath)) {
                    return Command::FAILURE;
                }
            }

            // $this->line('Git: Cleaning untracked files...');
            // Log::channel('deployment')->info('Git: Cleaning untracked files...');
            // if (!$this->runProcess('git clean -fd', $projectPath)) return Command::FAILURE;

            $this->line("Git: Pulling 'main' branch from {$expectedRepo}...");
            Log::channel('deployment')->info("Git: Pulling 'main' branch from {$expectedRepo}...");
            $pullCommand = "git pull {$remoteUrlWithCreds} main";
            if (! $this->runProcess($pullCommand, $projectPath)) {
                return Command::FAILURE;
            }

            if (app()->environment() !== 'local' && !$this->option('skip-dep')) {
                $noDev = app()->isProduction() ? '--no-dev' : '';
                $this->line('Composer: Installing dependencies...');
                Log::channel('deployment')->info('Composer: Installing dependencies...');
                $composerCommand
                    = "{$phpExecutable} /usr/local/bin/composer install --no-interaction {$noDev} --prefer-dist --optimize-autoloader";
                if (! $this->runProcess($composerCommand, $projectPath)) {
                    return Command::FAILURE;
                }
            }
            $artisanCommands = ["{$artisanScript} optimize:clear"];
            if (! app()->isProduction()) {
                $artisanCommands[] = "{$artisanScript} migrate:fresh";
            }
            $artisanCommands[] = "{$artisanScript} permissions:sync --guard=staff";
            $artisanCommands[] = "{$artisanScript} permissions:sync --guard=user";
            if (! app()->isProduction()) {
                $artisanCommands[] = "{$artisanScript} scribe:setup --fresh --seed";
                $artisanCommands[] = "{$artisanScript} db:seed --class=DemoSeeder";
            }
            $artisanCommands[] = "{$artisanScript} optimize";

            foreach ($artisanCommands as $command) {
                $this->line("Artisan: Running '{$command}'...");
                Log::channel('deployment')->info("Artisan: Running '{$command}'...");
                if (! $this->runProcess($command, $projectPath)) {
                    // Decide if one failed artisan command should stop the whole deployment
                    $this->error("✘ Artisan command '{$command}' failed.");
                    // return Command::FAILURE; // Uncomment to stop on first Artisan error
                }
            }

            $this->info('✅ Application deployed successfully!');
            Log::channel('deployment')->info('✅ Deployment completed successfully via Artisan command.');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('✘ General deployment exception: '.$e->getMessage());
            Log::channel('deployment')->error('✘ General deployment exception: '.$e->getMessage(),
                ['trace' => $e->getTraceAsString()]);

            return Command::FAILURE;
        }
    }

    /**
     * Helper function to run a process and log its output.
     * Returns true on success, false on failure.
     */
    protected function runProcess(string $command, string $path): bool
    {
        $process = Process::path($path)->run($command);

        if ($process->successful()) {
            $this->line($process->output()); // Output to console for manual runs
            Log::channel('deployment')->info("Success: {$command}", ['output' => $process->output()]);

            return true;
        }
        $this->error("Failed: {$command}");
        $this->error('Error Output: '.$process->errorOutput());
        Log::channel('deployment')
            ->error("Failed: {$command}", ['output' => $process->output(), 'error' => $process->errorOutput()]);

        return false;

    }
}
