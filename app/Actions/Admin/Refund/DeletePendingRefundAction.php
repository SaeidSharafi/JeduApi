<?php

namespace App\Actions\Admin\Refund;

use App\Enums\Order\RefundStatusEnum;
use App\Models\Refund;
use Illuminate\Validation\ValidationException;

class DeletePendingRefundAction
{
    public function handle(Refund $refund): void
    {
        if ($refund->status !== RefundStatusEnum::PENDING) {
            throw ValidationException::withMessages([
                'status' => __('messages.order.refund.only_pending_refunds_can_be_deleted'),
            ]);
        }

        $refund->delete();
    }
}
