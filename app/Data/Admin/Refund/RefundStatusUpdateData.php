<?php

declare(strict_types=1);

namespace App\Data\Admin\Refund;

use App\Enums\Order\RefundStatusEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class RefundStatusUpdateData extends Data
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $tracking_code,
        public readonly ?string $admin_notes,
    ) {}

    public static function rules(): array
    {
        return [
            'status'        => ['required', Rule::enum(RefundStatusEnum::class)],
            'tracking_code' => ['nullable', 'string', 'max:255'],
            'admin_notes'   => ['nullable', 'string'],
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
                'description' => 'Refund status value.',
                'example'     => 'approved',
            ],
            'tracking_code' => [
                'description' => 'Optional tracking code for the refund.',
                'example'     => 'TRK123456',
            ],
            'admin_notes' => [
                'description' => 'Optional admin notes for the refund.',
                'example'     => 'Refund processed successfully.',
            ],
        ];
    }
}
