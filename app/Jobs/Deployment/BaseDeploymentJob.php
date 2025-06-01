<?php

namespace App\Jobs\Deployment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

abstract class BaseDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Helper function to run a process and log its output.
     * Throws RuntimeException on failure.
     *
     * @param string $command The command to run.
     * @param string $path The working directory for the command.
     * @param int $timeout Timeout in seconds for the process.
     * @param array $env Environment variables for the process.
     * @return string The successful output of the command.
     * @throws RuntimeException If the process fails.
     */
    protected function runProcess(string $command, string $path, int $timeout = 240, array $env = []): string
    {
        Log::channel('deployment')->info("Executing: {$command}", ['path' => $path, 'timeout' => $timeout]);

        $process = Process::path($path)->timeout($timeout)->env($env)->run($command);

        if ($process->successful()) {
            $output = $process->output();
            Log::channel('deployment')->info("Success: {$command}", ['output' => $output]);
            // Optionally, you can echo to console if jobs are run synchronously for debugging
            // if (app()->runningInConsole() && !$this->job) { // Check if not a queued job for direct output
            //     echo $output . PHP_EOL;
            // }
            return $output;
        }

        $errorOutput = $process->errorOutput();
        $fullOutput = $process->output();
        Log::channel('deployment')->error("Failed: {$command}", [
            'output' => $fullOutput,
            'error'  => $errorOutput,
            'exit_code' => $process->exitCode(),
        ]);

        throw new RuntimeException(
            "Command '{$command}' failed with exit code {$process->exitCode()}.\nError Output: {$errorOutput}\nFull Output: {$fullOutput}"
        );
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        $jobName = class_basename($this); // Gets the name of the child job
        Log::channel('deployment')->error("💥 {$jobName} FAILED 💥", [
            'message'   => $exception->getMessage(),
            'exception' => get_class($exception),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $this->formatTrace($exception), // Limit trace length
            'job_id'    => $this->job?->getJobId() ?? 'N/A',
            'payload'   => $this->job?->payload() ?? 'N/A', // Be careful with sensitive data in payload
        ]);

        // Optionally, dispatch a notification job or perform other cleanup
        // Example: DeploymentNotificationJob::dispatch("{$jobName} failed: " . $exception->getMessage(), 'error');
    }

    /**
     * Formats the trace for logging, potentially truncating it.
     */
    protected function formatTrace(Throwable $exception, int $maxLength = 2000): string
    {
        $trace = $exception->getTraceAsString();
        if (strlen($trace) > $maxLength) {
            return substr($trace, 0, $maxLength) . "\n... (trace truncated)";
        }
        return $trace;
    }

    /**
     * Get the display name for the queued job.
     * Can be used by queue monitoring tools.
     *
     * @return string
     */
    public function displayName(): string
    {
        return class_basename($this);
    }
}
