<?php

declare(strict_types=1);

namespace App\Data\Admin\Payment;

use Spatie\LaravelData\Data;

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
