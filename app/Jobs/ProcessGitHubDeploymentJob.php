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
        Log::channel('deployment')->info('Deployment job started.', ['payload' => $this->webhookCall->payload]);

        // Optional: You can inspect $this->webhookCall->payload to make decisions
        // For example, only deploy if it's a push to the 'main' branch
        $payload = $this->webhookCall->payload;
        if (!isset($payload['ref']) || $payload['ref'] !== 'refs/heads/main') {
            Log::channel('deployment')->info('Deployment skipped: Not a push to main branch.', ['ref' => $payload['ref'] ?? 'N/A']);
            return;
        }
        try {
            Log::channel('deployment')->info('Attempting update using laravel-updater...');

            // Execute the update process (git pull + commands from laravel-updater config)
            // The Updater facade should handle logging internally as well if configured
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
