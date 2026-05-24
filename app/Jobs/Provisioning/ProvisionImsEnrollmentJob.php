<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\System\SettingKeyEnum;
use App\Enums\User\GenderEnum;
use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\AdminActionLog;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\Integrations\ImsService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ProvisionImsEnrollmentJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesProvisioningStatus;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $enrollmentId,
        public readonly ?int $paymentId = null,
    ) {}

    public function handle(ImsService $service, SettingsService $settings): void
    {
        $imsConfig = $settings->get(SettingKeyEnum::IMS);

        if (! ($imsConfig['enabled'] ?? false)) {
            return;
        }

        if (empty($imsConfig['base_url']) || empty($imsConfig['api_key'])) {
            throw new RuntimeException('IMS is enabled but configuration is missing.');
        }

        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $this->resolvePaymentOrFail($enrollment);

        $deliveryDetails = $enrollment->productDeliveryOption?->details_json ?? [];
        $imsCourseCode   = data_get($deliveryDetails, 'ims_course_code');
        if (! is_string($imsCourseCode) || $imsCourseCode === '') {
            throw new RuntimeException('IMS course code is missing from delivery option details.');
        }

        $customer          = $enrollment->customer;
        $orderItem         = $enrollment->orderItem;
        $discountAmount    = $orderItem?->discount_amount ?? 0;
        $orderItemAmount   = $orderItem?->total;
        $paymentAmount     = $this->resolvePaymentAmount($enrollment) > 0 ? $orderItemAmount : 0;
        $paymentDate       = $this->resolvePaymentDate($enrollment);
        $paymentDateString = $paymentDate?->toDateString();
        $student           = [
            'external_user_id' => (string) $customer->uuid,
            'first_name'       => $customer->first_name,
            'last_name'        => $customer->last_name,
            'phone'            => $customer->phone,
            'email'            => $customer->email,
            'national_code'    => $customer->civil_id,
            'father_name'      => $customer->father_name,
            'gender'           => $customer->gender === GenderEnum::MALE ? 1 : 0,
            'field_of_study'   => $customer->field_of_study,
            'education_level'  => $customer->education_level?->value,
            'education_status' => $customer->education_status?->value,
            'date_of_birth'    => $customer->date_of_birth?->format('Y-m-d'),
            'update_student'   => false,
        ];

        $enrolment = [
            'national_code' => $customer->civil_id,
            'course_code'   => $imsCourseCode,
            'payment'       => [
                'amount'              => $paymentAmount,
                'discount_type'       => $discountAmount > 0 ? 'manual' : 'none',
                'discount_amount'     => $discountAmount,
                'discount_code'       => $enrollment->order?->applied_coupon_code,
                'tracking_code'       => $this->resolvePaymentBill($enrollment),
                'date'                => $paymentDateString,
                'bank_account_number' => $this->resolveImsBankAccountNumber($enrollment),
            ],
            'note' => __('messages.online_enrolment').PHP_EOL.
                __('messages.order.order_number', ['order_id' => $enrollment->order?->increment_id])
                .PHP_EOL.
                $enrollment->notes,
        ];

        $student              = $service->storeSetudent($student);
        $enrolment            = $service->storeEnrolment($customer, $enrolment);
        $externalEnrollmentId = data_get($enrolment, 'data.enrollment_id');
        $externalEnrollmentId = is_scalar($externalEnrollmentId) ? (string) $externalEnrollmentId : null;

        $this->markProvisioningSuccess($enrollment, 'ims', [
            'course_code'   => $imsCourseCode,
            'enrollment_id' => $externalEnrollmentId,
            'student_id'    => data_get($student, 'data.student_id'),
            'created_at'    => data_get($enrolment, 'data.created_at'),
        ], $externalEnrollmentId);
    }

    public function failed(Throwable $exception): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $metaData = $exception instanceof ExternalProvisioningException
            ? $exception->metaData
            : [];

        $this->markProvisioningFailure($enrollment, 'ims', $exception->getMessage(), $metaData);

        Log::error('IMS provisioning failed', [
            'enrollment_id'     => $this->enrollmentId,
            'payment_id'        => $this->paymentId,
            'http_status'       => $metaData['http_status']       ?? null,
            'endpoint'          => $metaData['endpoint']          ?? null,
            'validation_errors' => $metaData['validation_errors'] ?? [],
            'exception_class'   => get_class($exception),
            'job_attempts'      => $this->attempts(),
        ]);

        AdminActionLog::create([
            'admin_id'        => null,
            'action_type'     => 'ims_provisioning_failed',
            'resource_type'   => Enrollment::class,
            'resource_id'     => $enrollment->id,
            'route_name'      => 'system:ims_provisioning',
            'http_method'     => 'QUEUE',
            'response_status' => $metaData['http_status'] ?? 0,
            'ip_address'      => '127.0.0.1',
            'risk_level'      => 'high',
            'metadata'        => [
                'endpoint'          => $metaData['endpoint']          ?? null,
                'validation_errors' => $metaData['validation_errors'] ?? [],
                'error_message'     => 'IMS validation failed',
                'raw_body_snippet'  => $metaData['raw_body_snippet'] ?? null,
                'job_attempts'      => $this->attempts(),
            ],
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180, 600];
    }

    private function findEnrollment(): ?Enrollment
    {
        return Enrollment::query()
            ->with(['customer', 'order', 'productDeliveryOption'])
            ->find($this->enrollmentId);
    }

    private function resolvePayment(Enrollment $enrollment): ?Payment
    {
        if ($this->paymentId !== null) {
            return Payment::query()->find($this->paymentId);
        }

        $order = $enrollment->order;

        return $order->payments()
            ->where('status', 'completed')
            ->latest('id')
            ->first();
    }

    private function resolvePaymentAmount(Enrollment $enrollment): int
    {
        $payment = $this->resolvePaymentOrFail($enrollment);

        return (int) $payment->amount;
    }

    private function resolvePaymentOrFail(Enrollment $enrollment): Payment
    {
        $order = $enrollment->order;
        if (! $order) {
            throw new RuntimeException('Enrollment order is required for IMS provisioning.');
        }

        $payment = $this->resolvePayment($enrollment);
        if (! $payment) {
            throw new RuntimeException('Completed payment is required for IMS provisioning.');
        }

        if ((int) $payment->order_id !== (int) $order->id) {
            throw new RuntimeException('Payment does not belong to enrollment order.');
        }

        if ($payment->status !== PaymentStatusEnum::COMPLETED) {
            throw new RuntimeException('Payment must be completed before IMS provisioning.');
        }

        return $payment;
    }

    private function resolvePaymentBill(Enrollment $enrollment): ?string
    {
        $payment = $this->resolvePayment($enrollment);

        $reference = $payment->last_gateway_reference
            ?? data_get($payment->data, 'transaction_id');

        return is_scalar($reference) ? (string) $reference : ($enrollment->order?->increment_id);
    }

    private function resolvePaymentDate(Enrollment $enrollment): ?Carbon
    {
        $payment = $this->resolvePayment($enrollment);
        if (! $payment) {
            return null;
        }

        $dataDate = data_get($payment->data, 'transaction_date');
        if (is_string($dataDate) && $dataDate !== '') {
            try {
                return Carbon::parse($dataDate);
            } catch (Throwable) {
                // Fallback to created_at when custom date cannot be parsed.
            }
        }

        return $payment->created_at ? Carbon::parse($payment->created_at) : null;
    }

    private function resolveImsBankAccountNumber(Enrollment $enrollment): ?string
    {
        $payment = $this->resolvePayment($enrollment);
        if (! $payment) {
            return null;
        }

        return match ($payment->method?->value) {
            'mellat_gateway' => config('payments.mellat.ims_bank_account_number'),
            'bank_transfer'  => config('payments.bank_transfer.ims_bank_account_number'),
            'wallet'         => config('payments.wallet.ims_bank_account_number'),
            default          => null,
        };
    }
}
