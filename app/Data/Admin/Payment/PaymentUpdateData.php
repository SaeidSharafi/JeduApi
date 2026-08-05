<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use App\Enums\Payment\PaymentStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class PaymentUpdateData extends Data
{
    public function __construct(
        public ?string $status = null,
        public ?string $admin_notes = null,
    ) {}

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, array<string, mixed>>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'status'      => ['nullable', 'string', Rule::enum(PaymentStatusEnum::class)],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
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
            'status' => [
                'description' => 'Payment status value.',
                'example'     => 'completed',
            ],
            'admin_notes' => [
                'description' => 'Optional admin notes for the payment.',
                'example'     => 'Payment confirmed by admin.',
            ],
        ];
    }
}
