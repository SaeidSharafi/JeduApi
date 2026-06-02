<?php

declare(strict_types=1);

namespace App\Actions\Admin\Enrollment;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Jobs\Provisioning\ProvisionMoodleQuizJob;
use App\Jobs\Provisioning\ProvisionSkyroomEnrollmentJob;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Enrollment;
use Illuminate\Validation\ValidationException;

final readonly class RetryProvisioningAction
{
    /**
     * Execute the action.
     *
     * @return array{message: string, providers: array<int, string>}
     */
    public function handle(Enrollment $enrollment): array
    {
        if (
            $enrollment->enrollment_status    !== EnrollmentStatusEnum::PROVISIONING_FAILED
            && $enrollment->enrollment_status !== EnrollmentStatusEnum::PENDING_PROVISIONING
        ) {
            throw ValidationException::withMessages([
                'enrollment_status' => sprintf(
                    'Cannot retry provisioning for enrollment with status: %s',
                    $enrollment->enrollment_status->value
                ),
            ]);
        }

        $failedProviders = $this->getFailedProviders($enrollment);

        if (empty($failedProviders)) {
            throw ValidationException::withMessages([
                'provisioning_data' => 'No failed providers found to retry',
            ]);
        }

        $dispatchedProviders = $this->dispatchProvisioningJobs($enrollment, $failedProviders);

        return [
            'message'   => sprintf('Retry dispatched for %d provider(s)', count($dispatchedProviders)),
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
        $dispatched     = [];
        $deliveryMethod = $enrollment->productDeliveryOption?->delivery_method;
        $detailsJson    = $enrollment->productDeliveryOption?->details_json ?? [];

        foreach ($failedProviders as $provider) {
            if ($provider === 'ims') {
                $imsCourseCode = data_get($detailsJson, 'ims_course_code');
                if (is_string($imsCourseCode) && $imsCourseCode !== '') {
                    $paymentId = $this->resolvePaymentId($enrollment);
                    ProvisionImsEnrollmentJob::dispatch($enrollment->id, $paymentId);
                    $dispatched[] = 'ims';
                }
            } elseif ($provider === 'moodle' && $deliveryMethod === DeliveryMethodEnum::LMS_MOODLE) {
                ProvisionMoodleEnrollmentJob::dispatch($enrollment->id);
                $dispatched[] = 'moodle';
            } elseif ($provider === 'spotplayer' && $deliveryMethod === DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER) {
                ProvisionSpotPlayerEnrollmentJob::dispatch($enrollment->id);
                $dispatched[] = 'spotplayer';
            } elseif ($provider === 'bbb' && $deliveryMethod === DeliveryMethodEnum::LIVE_SESSION_BBB) {
                ProvisionBbbEnrollmentJob::dispatch($enrollment->id);
                $dispatched[] = 'bbb';
            } elseif ($provider === 'skyroom' && $deliveryMethod === DeliveryMethodEnum::LIVE_SESSION_SKYROOM) {
                ProvisionSkyroomEnrollmentJob::dispatch($enrollment->id);
                $dispatched[] = 'skyroom';
            } elseif ($provider === 'moodle_quiz') {
                $moodleQuizCourseId = data_get($detailsJson, 'moodle_quiz_course_id');
                if (
                    $deliveryMethod !== DeliveryMethodEnum::LMS_MOODLE
                    && is_numeric($moodleQuizCourseId)
                ) {
                    ProvisionMoodleQuizJob::dispatch($enrollment->id);
                    $dispatched[] = 'moodle_quiz';
                }
            }
        }

        return $dispatched;
    }

    /**
     * Resolve the latest completed payment ID.
     */
    private function resolvePaymentId(Enrollment $enrollment): ?int
    {
        $payment = $enrollment->order?->payments()
            ->where('status', PaymentStatusEnum::COMPLETED)
            ->latest('id')
            ->first();

        return $payment?->id;
    }
}
