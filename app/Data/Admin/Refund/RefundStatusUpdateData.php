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
}
