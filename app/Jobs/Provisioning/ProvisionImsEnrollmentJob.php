<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\User\GenderEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\AdminActionLog;
use App\Models\Enrollment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations\ImsService;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

final class ProvisionImsEnrollmentJob extends AbstractProvisioningJob
{
    use \Illuminate\Foundation\Queue\Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $enrollmentId,
        public readonly ?int $paymentId = null,
    ) {}

    protected function resolveEnrollment(): ?Enrollment
    {
        return Enrollment::query()
            ->with(['customer', 'order', 'productDeliveryOption'])
            ->find($this->enrollmentId);
    }

    protected function getIntegrationName(): string
    {
        return 'ims';
    }

    protected function executeProvisioning(): void
    {
        /** @var ImsService $service */
        $service = app(ImsService::class);

        if (! $service->isEnabled()) {
            return;
        }

        $service->assertConfigured();

        $enrollment = $this->getEnrollment();
        if (! $enrollment) {
            return;
        }

        $this->resolvePaymentOrFail($enrollment);

        $deliveryDetails = $enrollment->productDeliveryOption?->details_json ?? [];

        $imsCourseCode = data_get($enrollment->productDeliveryOption?->details_json ?? [], 'ims_course_code');
        if (empty($imsCourseCode)) {
            throw new UnrecoverableProvisioningException('IMS course code is missing from delivery option details.');
        }

        /** @var User $customer */
        $customer = $enrollment->customer;
        /** @var ?OrderItem $orderItem */
        $orderItem         = $enrollment->orderItem;
        $discountAmount    = $orderItem->discount_amount ?? 0;
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
            'civil_id'         => $customer->civil_id,
            'civil_id_type'    => $customer->civil_id_type,
            'father_name'      => $customer->father_name,
            'gender'           => $customer->gender === GenderEnum::MALE ? 1 : 0,
            'field_of_study'   => $customer->field_of_study,
            'education_level'  => $customer->education_level?->value,
            'education_status' => $customer->education_status?->value,
            'date_of_birth'    => $customer->date_of_birth?->format('Y-m-d'),
            'update_student'   => false,
        ];

        $enrollmentData = [
            'civil_id'      => $customer->civil_id,
            'civil_id_type' => $customer->civil_id_type,
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
            'note' => __('messages.online_enrollment').PHP_EOL.
                __('messages.order.order_number', ['order_id' => $enrollment->order?->increment_id])
                .PHP_EOL.
                $enrollment->notes,
        ];

        $studentData    = $service->storeStudent($student);
        $enrollmentData = $service->storeEnrollment($enrollment->customer, $enrollmentData);

        $externalEnrollmentId = data_get($enrollmentData, 'data.enrollment_id');
        $externalEnrollmentId = is_scalar($externalEnrollmentId) ? (string) $externalEnrollmentId : null;

        $this->markProvisioningSuccess($enrollment, 'ims', [
            'course_code'   => $imsCourseCode,
            'enrollment_id' => $externalEnrollmentId,
            'student_id'    => data_get($studentData, 'data.student_id'),
            'created_at'    => data_get($enrollmentData, 'data.created_at'),
        ], $externalEnrollmentId);
    }

    protected function onFailed(Enrollment $enrollment, Throwable $exception, array $metaData): void
    {
        // Compute a static sanitized error message — never leak raw exception text into admin logs.
        $errorMessage = ($metaData['http_status'] ?? 0) === 422
            ? 'IMS validation failed'
            : 'IMS provisioning failed';

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
            'metadata'        => array_merge($metaData, [
                'job_attempts'  => $this->attempts(),
                'error_message' => $errorMessage,
            ]),
        ]);
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

        return match ($payment->method) {
            PaymentMethodEnum::MELLAT_GATEWAY => config('payments.mellat.ims_bank_account_number'),
            PaymentMethodEnum::BANK_TRANSFER => config('payments.bank_transfer.ims_bank_account_number'),
            PaymentMethodEnum::WALLET => config('payments.wallet.ims_bank_account_number'),
            default => null,
        };
    }
}
