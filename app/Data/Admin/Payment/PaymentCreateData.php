<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use App\Enums\Payment\PaymentMethodEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PaymentCreateData extends Data
{
    public function __construct(
        public string $method,
        public string $status,
        public ?BankTransferPaymentData $data,
        public ?string $admin_notes,

    ) {}

    public static function rules(ValidationContext $context): array
    {
        $now = verta()->format('Y-m-d');

        return [
            'method'      => ['required', Rule::enum(PaymentMethodEnum::class)],
            'status'      => ['required', 'string'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'data'        => ['nullable', 'array'],
            // Bank transfer validation
            'data.transaction_id'   => ['nullable', 'string', 'max:255'],
            'data.transaction_date' => ['nullable', 'jdate:Y-m-d', 'jdate_before_equal:'.$now.',Y-m-d'],
            'data.sender_name'      => ['nullable', 'string', 'max:255'],
            'data.notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'method' => [
                'description' => 'Payment method used for the transaction',
                'example'     => 'credit_card',
            ],
            'status' => [
                'description' => 'Current status of the payment',
                'example'     => 'pending',
            ],
            'data' => [
                'description' => 'Additional data related to the payment, such as transaction ID or gateway response',
                'example'     => ['transaction_id' => '123456789'],
            ],
            'admin_notes' => [
                'description' => 'Optional notes for administrative purposes',
                'example'     => 'Payment received, awaiting confirmation.',
            ],
            'wallet_data' => [
                'description' => 'Wallet payment data when using wallet as payment method',
                'example'     => ['wallet_id' => 1, 'amount' => 50000, 'description' => 'Order payment'],
            ],
        ];
    }
}
