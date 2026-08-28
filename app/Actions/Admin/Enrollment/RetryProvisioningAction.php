<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Jobs\Provisioning\ProvisionMoodleQuizJob;
use App\Jobs\Provisioning\ProvisionSkyroomEnrollmentJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Enrollment;
use App\Services\Enrollment\ProvisioningPlanResolver;
use App\Services\Provisioning\ProvisioningAttemptService;
use Illuminate\Validation\ValidationException;

final readonly class RetryProvisioningAction
{
    public function __construct(
        private ProvisioningPlanResolver $planResolver,
        private ProvisioningAttemptService $attemptService,
    ) {}

    /**
     * Execute the action.
     *
     * @return array{message: string, providers: array<int, string>}
     */
    public function handle(Enrollment $enrollment, ?string $provider = null): array
    {
        if (
            $enrollment->enrollment_status    !== EnrollmentStatusEnum::PROVISIONING_FAILED
            && $enrollment->enrollment_status !== EnrollmentStatusEnum::PENDING_PROVISIONING
        ) {
            throw ValidationException::withMessages([
                'enrollment_status' => __('messages.enrollments.retry_provisioning_not_allowed',
                    ['status' => $enrollment->enrollment_status->translate()]),
            ]);
        }

        // If provisioning_data is null, this enrollment was never provisioned
        // (queue failure, event listener crash, etc.). Dispatch all required providers.
        if ($enrollment->provisioning_data === null) {
            $dispatchedProviders = $this->dispatchAllRequiredProviders($enrollment, $provider);

            return [
                'message'   => __('messages.enrollments.initial_provisioning_dispatched', ['count' => count($dispatchedProviders)]),
                'providers' => $dispatchedProviders,
            ];
        }

        $failedProviders = $this->getFailedProviders($enrollment);
        if ($provider !== null) {
            if (! in_array($provider, $failedProviders, true)) {
                throw ValidationException::withMessages([
                    'provider' => __('messages.enrollments.no_failed_providers'),
                ]);
            }
            $failedProviders = [$provider];
        }

        if (empty($failedProviders)) {
            throw ValidationException::withMessages([
                'provisioning_data' => __('messages.enrollments.no_failed_providers'),
            ]);
        }

        $dispatchedProviders = $this->dispatchProvisioningJobs($enrollment, $failedProviders);

        return [
            'message'   => __('messages.enrollments.retry_dispatched', ['count' => count($dispatchedProviders)]),
            'providers' => $dispatchedProviders,
        ];
    }

    /**
     * Get failed providers from provisioning_data.
     *
     * @return array<int, string>
     */
    private function getFailedProviders(Enrollment $enrollment): array
    {
        $provisioningData = $enrollment->provisioning_data ?? [];
        $providers        = $provisioningData['providers'] ?? [];

        $failed = [];
        foreach ($providers as $key => $providerData) {
            if (is_array($providerData) && ($providerData['status'] ?? null) === 'failed') {
                $failed[] = $key;
            }
        }

        return $failed;
    }

    /**
     * Dispatch provisioning jobs for failed providers.
     *
     * @param  array<int, string>  $failedProviders
     * @return array<int, string>
     */
    private function dispatchProvisioningJobs(Enrollment $enrollment, array $failedProviders): array
    {
        $dispatched       = [];
        $plannedProviders = $this->plannedProviders($enrollment)
            ->pluck('provider')
            ->all();

        foreach ($failedProviders as $provider) {
            if (! in_array($provider, $plannedProviders, true)) {
                continue;
            }

            if ($provider === 'ims') {
                $this->dispatchProvider($enrollment, ProvisioningProviderEnum::IMS);
                $dispatched[] = 'ims';
            } elseif ($provider === 'moodle') {
                $this->dispatchProvider($enrollment, ProvisioningProviderEnum::MOODLE);
                $dispatched[] = 'moodle';
            } elseif ($provider === 'spotplayer') {
                ProvisionSpotPlayerEnrollmentJob::dispatch($enrollment->id);
                $dispatched[] = 'spotplayer';
            } elseif ($provider === 'bbb') {
                ProvisionBbbEnrollmentJob::dispatch($enrollment->id);
                $dispatched[] = 'bbb';
            } elseif ($provider === 'skyroom') {
                ProvisionSkyroomEnrollmentJob::dispatch($enrollment->id);
                $dispatched[] = 'skyroom';
            } elseif ($provider === 'moodle_quiz') {
                ProvisionMoodleQuizJob::dispatch($enrollment->id);
                $dispatched[] = 'moodle_quiz';
            }
        }

        return $dispatched;
    }

    /**
     * Dispatch all required provisioning jobs (for null provisioning_data case).
     *
     * This handles the edge case where provisioning was never attempted
     * (queue failure, event listener crash, etc.).
     *
     * @return array<int, string>
     */
    private function dispatchAllRequiredProviders(Enrollment $enrollment, ?string $provider = null): array
    {
        $dispatched       = [];
        $plannedProviders = $this->plannedProviders($enrollment)
            ->pluck('provider');
        if ($provider !== null) {
            $plannedProviders = $plannedProviders->filter(fn (string $planned): bool => $planned === $provider);
        }

        if ($plannedProviders->contains('ims')) {
            $this->dispatchProvider($enrollment, ProvisioningProviderEnum::IMS);
            $dispatched[] = 'ims';
        }

        if ($plannedProviders->contains('moodle')) {
            $this->dispatchProvider($enrollment, ProvisioningProviderEnum::MOODLE);
            $dispatched[] = 'moodle';
        }

        if ($plannedProviders->contains('spotplayer')) {
            ProvisionSpotPlayerEnrollmentJob::dispatch($enrollment->id);
            $dispatched[] = 'spotplayer';
        }

        if ($plannedProviders->contains('bbb')) {
            ProvisionBbbEnrollmentJob::dispatch($enrollment->id);
            $dispatched[] = 'bbb';
        }

        if ($plannedProviders->contains('skyroom')) {
            ProvisionSkyroomEnrollmentJob::dispatch($enrollment->id);
            $dispatched[] = 'skyroom';
        }

        if ($plannedProviders->contains('moodle_quiz')) {
            ProvisionMoodleQuizJob::dispatch($enrollment->id);
            $dispatched[] = 'moodle_quiz';
        }

        return $dispatched;
    }

    private function dispatchProvider(Enrollment $enrollment, ProvisioningProviderEnum $provider): void
    {
        $attempt = $this->attemptService->queue(
            $enrollment,
            ProvisioningTriggerEnum::RETRY,
            auth('staff')->id(),
            $provider,
        );
        ProvisionEnrollmentProviderJob::dispatch($attempt->id);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{provider: string, readiness: string}>
     */
    private function plannedProviders(Enrollment $enrollment): \Illuminate\Support\Collection
    {
        $plan = $enrollment->provisioning_plan;
        if (is_array($plan) && array_key_exists('version', $plan)) {
            return collect($plan['providers'] ?? []);
        }

        $resolvedPlan = $this->planResolver->resolve($enrollment->productDeliveryOption);

        return collect($resolvedPlan['providers']);
    }
}
