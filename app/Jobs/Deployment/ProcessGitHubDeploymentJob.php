<?php

declare(strict_types=1);

namespace App\Jobs\Deployment;

use Exception;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;
use Throwable;

final class ProcessGitHubDeploymentJob extends SpatieProcessWebhookJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload   = $this->webhookCall->payload;
        $eventName = $this->webhookCall->headerBag()->get('X-GitHub-Event');

        Log::channel('deployment')->info('GitHub Webhook received.', [
            'event'          => $eventName,
            'delivery_id'    => $this->webhookCall->headerBag()->get('X-GitHub-Delivery'),
            'payload_action' => $payload['action'] ?? 'N/A',
        ]);

        if ($eventName !== 'workflow_run' || ! isset($payload['action']) || $payload['action'] !== 'completed') {
            Log::channel('deployment')->info('Skipping: Not a completed workflow_run event.');

            return;
        }

        $workflowRun = $payload['workflow_run'];
        if ($workflowRun['conclusion'] !== 'success') {
            Log::channel('deployment')->info('Skipping: Workflow run did not conclude with success.', ['conclusion' => $workflowRun['conclusion']]);

            return;
        }

        if ($workflowRun['head_branch'] !== 'main') {
            Log::channel('deployment')->info('Skipping: Not for the main branch.', ['branch' => $workflowRun['head_branch']]);

            return;
        }

        Log::channel('deployment')->info('Conditions met. Proceeding with deployment via laravel-updater.', [
            'commit_sha' => $workflowRun['head_commit']['id'] ?? 'N/A',
        ]);

        try {
            $projectPath  = base_path();
            $gitUsername  = config('app.git_deploy_username');
            $gitPat       = config('app.git_deploy_pat');
            $expectedRepo = 'SaeidSharafi/JeduApi';

            if (empty($gitUsername) || empty($gitPat)) {
                Log::channel('deployment')->error('✘ Git username or PAT not configured.');
                $this->fail(new Exception('Git credentials not configured for deployment pipeline.'));

                return;
            }
            $remoteUrlWithCreds = "https://{$gitUsername}:{$gitPat}@github.com/{$expectedRepo}.git";

            // Dispatch the first job in the sequence
            GitPullJob::dispatch(
                $projectPath,
                $remoteUrlWithCreds,
                $expectedRepo
            )->allOnQueue($this->queue)
                ->chain([
                    new ComposerInstallJob($projectPath),
                    new MigrationSetupJob($projectPath),
                    new ScribeSetupJob($projectPath),
                    new DemoSeedJob($projectPath),
                ]);

            Log::channel('deployment')->info('Deployment job chain dispatched successfully by ProcessGitHubDeploymentJob.');

        } catch (Throwable $e) { // Changed to Throwable
            Log::channel('deployment')->error('Failed to dispatch initial deployment job: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->fail($e);

            return;
        }

        Log::channel('deployment')->info('ProcessGitHubDeploymentJob finished (dispatched first sub-job).');
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::channel('deployment')->error('💥 ProcessGitHubDeploymentJob FAILED (Orchestrator): '.$exception->getMessage(), [
            'exception_details' => (string) $exception,
        ]);
    }
}
