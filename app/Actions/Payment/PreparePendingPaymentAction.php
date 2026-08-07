<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final readonly class PreparePendingPaymentAction
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function handle(
        Authenticatable $actor,
        int $customerId,
        PaymentMethodEnum $method,
        PaymentPurposeEnum $purpose,
        int $amount,
        ?Order $order = null,
        ?string $adminNotes = null,
        ?array $data = null
    ): Payment {
        $createdBy = $actor instanceof Staff ? $actor->id : null;

        return DB::transaction(function () use (
            $order,
            $customerId,
            $method,
            $purpose,
            $amount,
            $data,
            $createdBy,
            $adminNotes,
        ) {

            $attemptCount = $order !== null
                ? $order->payments()->where('method', $method)->count() + 1
                : 1;

            return Payment::create([
                'order_id'          => $order?->id,
                'customer_id'       => $customerId,
                'amount'            => $amount,
                'method'            => $method->value,
                'purpose'           => $purpose->value,
                'status'            => PaymentStatusEnum::PENDING->value,
                'admin_notes'       => $adminNotes,
                'data'              => $data,
                'created_by'        => $createdBy,
                'attempt_count'     => $attemptCount,
                'last_attempted_at' => now(),
                'ip_address'        => request()->ip(),
                'user_agent'        => request()->userAgent(),
            ]);
        });
    }
}
