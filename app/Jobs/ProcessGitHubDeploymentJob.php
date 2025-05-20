<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Salahhusa9\Updater\Facades\Updater;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessGitHubDeploymentJob extends SpatieProcessWebhookJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $eventName = $this->webhookCall->header('X-GitHub-Event');

        Log::channel('deployment')->info('GitHub Webhook received.', [
            'event' => $eventName,
            'delivery_id' => $this->webhookCall->header('X-GitHub-Delivery'),
            'payload_action' => $payload['action'] ?? 'N/A',
        ]);

        if ($eventName !== 'workflow_run' || !isset($payload['action']) || $payload['action'] !== 'completed') {
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
            'commit_sha' => $workflowRun['head_commit']['id'] ?? 'N/A'
        ]);

        try {
            Log::channel('deployment')->info('Attempting update using laravel-updater...');

            Updater::update();

            Log::channel('deployment')->info('Laravel Updater process completed.');

        } catch (\Exception $e) {
            Log::channel('deployment')->error('Deployment failed via laravel-updater: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // $this->fail($e); // Mark the job as failed to retry if applicable
            return;
        }


        Log::channel('deployment')->info('Deployment job finished successfully.');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('deployment')->error('DEPLOYMENT JOB FAILED: ' . $exception->getMessage());
        // Send notification to admin
    }
}
