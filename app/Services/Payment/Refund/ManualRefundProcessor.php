<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Contracts\Payment\RefundProcessorInterface;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\Log;

final class ManualRefundProcessor implements RefundProcessorInterface
{
    public function process(Refund $refund, Order $order, int $amount): ?string
    {
        Log::info('[Manual Refund] Admin must wire money out-of-band', [
            'order_id'  => $order->id,
            'refund_id' => $refund->id,
            'amount'    => $amount,
            'method'    => $order->payments()->oldest()->value('method'),
        ]);

        return null;
    }
}
