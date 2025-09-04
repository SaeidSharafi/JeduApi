<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProcessorContract;
use App\Data\Admin\Payment\PaymentCreateData;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Events\PaymentCompletedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BankTransferPaymentProcessor implements PaymentProcessorContract
{
    public function canHandle(PaymentMethodEnum $paymentMethod): bool
    {
        return $paymentMethod === PaymentMethodEnum::BANK_TRANSFER;
    }

    public function process(
        Order $order,
        PaymentCreateData $paymentData,
        Authenticatable $adminUser,
        int $amountToPay
    ): Payment {
        if ($amountToPay > 0) {
            $this->validateBankTransferDetails($paymentData);
        }

        // Create payment record
        $payment = $order->payments()->create([
            'customer_id' => $order->customer_id,
            'created_by'  => $adminUser instanceof Staff ? $adminUser->id : null,
            'amount'      => $amountToPay,
            'method'      => $paymentData->method,
            'status'      => $paymentData->status,
            'admin_notes' => $paymentData->admin_notes,
            'data'        => $paymentData->data?->toArray()
        ]);

        // Dispatch completion event if payment is completed
        if ($payment->status === PaymentStatusEnum::COMPLETED) {
            PaymentCompletedEvent::dispatch($payment);
        }

        return $payment;
    }

    /**
     * Throws a ValidationException if required bank transfer details are missing.
     *
     * @throws ValidationException
     */
    private function validateBankTransferDetails(PaymentCreateData $paymentData): void
    {
        $dataToValidate = [
            'data' => $paymentData->data?->toArray() ?? [],
        ];

        $rules = [
            'data.transaction_id'   => ['required', 'string', 'max:255'],
            'data.transaction_date' => [
                'required', 'date:Y-m-d', Rule::date()->beforeOrEqual(today())
            ],
            'data.sender_name'      => ['required', 'string', 'max:255'],
            'data.notes'            => ['nullable', 'string', 'max:1000'],
        ];

        Validator::make($dataToValidate, $rules)->validate();
    }
}
