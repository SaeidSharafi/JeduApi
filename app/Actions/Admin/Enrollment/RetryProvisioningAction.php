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
                'enrollment_status' => __('messages.enrollments.retry_provisioning_not_allowed',
                    ['status' => $enrollment->enrollment_status->translate()]),
            ]);
        }

        // If provisioning_data is null, this enrollment was never provisioned
        // (queue failure, event listener crash, etc.). Dispatch all required providers.
        if ($enrollment->provisioning_data === null) {
            $dispatchedProviders = $this->dispatchAllRequiredProviders($enrollment);

            return [
                'message'   => __('messages.enrollments.initial_provisioning_dispatched', ['count' => count($dispatchedProviders)]),
                'providers' => $dispatchedProviders,
            ];
        }

        $failedProviders = $this->getFailedProviders($enrollment);

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

    /**
     * Dispatch all required provisioning jobs (for null provisioning_data case).
     *
     * This handles the edge case where provisioning was never attempted
     * (queue failure, event listener crash, etc.).
     *
     * @return array<int, string>
     */
    private function dispatchAllRequiredProviders(Enrollment $enrollment): array
    {
        $dispatched     = [];
        $deliveryMethod = $enrollment->productDeliveryOption?->delivery_method;
        $detailsJson    = $enrollment->productDeliveryOption?->details_json ?? [];

        // IMS - if ims_course_code exists
        $imsCourseCode = data_get($detailsJson, 'ims_course_code');
        if (is_string($imsCourseCode) && $imsCourseCode !== '') {
            $paymentId = $this->resolvePaymentId($enrollment);
            ProvisionImsEnrollmentJob::dispatch($enrollment->id, $paymentId);
            $dispatched[] = 'ims';
        }

        // Moodle - if delivery method is LMS_MOODLE
        if ($deliveryMethod === DeliveryMethodEnum::LMS_MOODLE) {
            ProvisionMoodleEnrollmentJob::dispatch($enrollment->id);
            $dispatched[] = 'moodle';
        }

        // SpotPlayer - if delivery method is VIDEO_PLATFORM_SPOTPLAYER
        if ($deliveryMethod === DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER) {
            ProvisionSpotPlayerEnrollmentJob::dispatch($enrollment->id);
            $dispatched[] = 'spotplayer';
        }

        // BBB - if delivery method is LIVE_SESSION_BBB
        if ($deliveryMethod === DeliveryMethodEnum::LIVE_SESSION_BBB) {
            ProvisionBbbEnrollmentJob::dispatch($enrollment->id);
            $dispatched[] = 'bbb';
        }

        // Skyroom - if delivery method is LIVE_SESSION_SKYROOM
        if ($deliveryMethod === DeliveryMethodEnum::LIVE_SESSION_SKYROOM) {
            ProvisionSkyroomEnrollmentJob::dispatch($enrollment->id);
            $dispatched[] = 'skyroom';
        }

        // Moodle Quiz - if delivery is NOT LMS_MOODLE and moodle_quiz_course_id exists
        $moodleQuizCourseId = data_get($detailsJson, 'moodle_quiz_course_id');
        if (
            $deliveryMethod !== DeliveryMethodEnum::LMS_MOODLE
            && is_numeric($moodleQuizCourseId)
        ) {
            ProvisionMoodleQuizJob::dispatch($enrollment->id);
            $dispatched[] = 'moodle_quiz';
        }

        return $dispatched;
    }
}
