<?php

declare(strict_types=1);

namespace App\Services\Provisioning\Providers;

use App\Contracts\Provisioning\ProvisioningProvider;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\User\GenderEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Services\Integrations\ImsService;
use Illuminate\Support\Carbon;

final readonly class ImsProvisioningProvider implements ProvisioningProvider
{
    public function __construct(private ImsService $ims) {}

    public function provider(): ProvisioningProviderEnum
    {
        return ProvisioningProviderEnum::IMS;
    }

    public function provision(Enrollment $enrollment): array
    {
        if (! $this->ims->isEnabled()) {
            throw new UnrecoverableProvisioningException('IMS provider is disabled.');
        }
        $this->ims->assertConfigured();
        $enrollment = $enrollment->fresh(['customer', 'order', 'orderItem', 'productDeliveryOption']);
        $code       = data_get($enrollment->productDeliveryOption?->details_json, 'ims_course_code');
        if (! is_string($code) || $code === '') {
            throw new UnrecoverableProvisioningException(__('messages.provisioning.ims_course_code_missing'));
        }
        $payment = $enrollment->order?->payments()->where('status', PaymentStatusEnum::COMPLETED)->latest('id')->first();
        if (! $payment) {
            throw new UnrecoverableProvisioningException(__('messages.provisioning.completed_payment_required'));
        }
        $customer = $enrollment->customer;
        $student  = $this->ims->storeStudent([
            'external_user_id' => (string) $customer->uuid, 'first_name' => $customer->first_name,
            'last_name'        => $customer->last_name, 'phone' => $customer->phone, 'email' => $customer->email,
            'civil_id'         => $customer->civil_id, 'civil_id_type' => $customer->civil_id_type,
            'father_name'      => $customer->father_name, 'gender' => $customer->gender === GenderEnum::MALE ? 1 : 0,
            'field_of_study'   => $customer->field_of_study, 'education_level' => $customer->education_level?->value,
            'education_status' => $customer->education_status?->value, 'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
            'update_student'   => false,
        ]);
        try {
            $result = $this->ims->storeEnrollment($customer, [
                'civil_id' => $customer->civil_id, 'civil_id_type' => $customer->civil_id_type, 'course_code' => $code,
                'payment'  => [
                    'amount'              => (int) $payment->amount                                  > 0 ? (int) ($enrollment->orderItem?->total ?? 0) : 0,
                    'discount_type'       => ((int) ($enrollment->orderItem?->discount_amount ?? 0)) > 0 ? 'manual' : 'none',
                    'discount_amount'     => (int) ($enrollment->orderItem?->discount_amount ?? 0),
                    'discount_code'       => $enrollment->order?->applied_coupon_code,
                    'tracking_code'       => $payment->last_gateway_reference ?? data_get($payment->data, 'transaction_id') ?? $enrollment->order?->increment_id,
                    'date'                => $payment->created_at?->toDateString(),
                    'bank_account_number' => match ($payment->method) {
                        PaymentMethodEnum::MELLAT_GATEWAY => config('payments.mellat.ims_bank_account_number'),
                        PaymentMethodEnum::BANK_TRANSFER  => config('payments.bank_transfer.ims_bank_account_number'),
                        PaymentMethodEnum::WALLET         => config('payments.wallet.ims_bank_account_number'),
                        default                           => null,
                    },
                ],
                'note' => __('messages.online_enrollment'),
            ]);
        } catch (RecoverableProvisioningException $exception) {
            throw new UnrecoverableProvisioningException('IMS outcome is ambiguous; manual verification required.', 0, $exception, array_merge($exception->metaData, ['ambiguous_outcome' => true]));
        }

        return [
            'course_code'       => $code,
            'ims_student_id'    => data_get($student, 'data.student_id'),
            'ims_enrollment_id' => data_get($result, 'data.enrollment_id'),
            'provisioned_at'    => Carbon::now()->toISOString(),
        ];
    }
}
