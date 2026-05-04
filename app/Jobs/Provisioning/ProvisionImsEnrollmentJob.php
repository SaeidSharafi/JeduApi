<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Enums\Payment\PaymentStatusEnum;
use App\Jobs\Provisioning\Concerns\HandlesProvisioningStatus;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\Integrations\ImsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function handle(ImsService $imsService): void
    {
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

        $customer        = $enrollment->customer;
        $orderItem       = $enrollment->orderItem;
        $discountAmount  = $orderItem?->discount_amount ?? 0;
        $orderItemAmount = $orderItem?->total;
        $paymentAmount   = $this->resolvePaymentAmount($enrollment) > 0 ? $orderItemAmount : 0;
        $paymentDate       = $this->resolvePaymentDate($enrollment);
        $paymentDateString = $paymentDate?->toDateString();

        $payload = [
            'student' => [
                'external_user_id' => (string) $customer->uuid,
                'first_name'       => $customer->first_name,
                'last_name'        => $customer->last_name,
                'phone'            => $customer->phone,
                'email'            => $customer->email,
                'national_code'    => $customer->civil_id,
                'father_name'      => $customer->father_name,
                'gender'           => $customer->gender?->value,
                'field_of_study'   => $customer->field_of_study,
                'date_of_birth'    => $customer->date_of_birth?->format('Y-m-d'),
            ],
            'registrations' => [
                [
                    'course_code'        => $imsCourseCode,
                    'enrollment_uuid'    => $enrollment->uuid,
                    'order_increment_id' => $enrollment->order?->increment_id,
                    'payment'            => [
                        'amount'              => $paymentAmount,
                        'discount_type'       => $discountAmount > 0 ? 'manual' : 'none',
                        'discount_amount'     => $discountAmount,
                        'discount_code'       => $enrollment->order?->applied_coupon_code,
                        'bill'                => $this->resolvePaymentBill($enrollment),
                        'date'                => $paymentDateString,
                        'bank_account_number' => $this->resolveImsBankAccountNumber($enrollment),
                    ],
                    'note' => $enrollment->notes,
                ],
            ],
        ];

        $result = $imsService->provisionEnrollment($payload);

        $externalEnrollmentId = data_get($result, 'data.enrollment_id');
        $externalEnrollmentId = is_scalar($externalEnrollmentId) ? (string) $externalEnrollmentId : null;

        $this->markProvisioningSuccess($enrollment, 'ims', [
            'course_code' => $imsCourseCode,
            'response'    => $result,
        ], $externalEnrollmentId);
    }

    public function failed(Throwable $exception): void
    {
        $enrollment = $this->findEnrollment();
        if (! $enrollment) {
            return;
        }

        $this->markProvisioningFailure($enrollment, 'ims', $exception->getMessage());
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

        return is_scalar($reference) ? (string) $reference : null;
    }

    private function resolvePaymentDate(Enrollment $enrollment): ?\Illuminate\Support\Carbon
    {
        $payment = $this->resolvePayment($enrollment);
        if (! $payment) {
            return null;
        }

        $dataDate = data_get($payment->data, 'transaction_date');
        if (is_string($dataDate) && $dataDate !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($dataDate);
            } catch (Throwable) {
                // Fallback to created_at when custom date cannot be parsed.
            }
        }

        return $payment->created_at ? \Illuminate\Support\Carbon::parse($payment->created_at) : null;
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
