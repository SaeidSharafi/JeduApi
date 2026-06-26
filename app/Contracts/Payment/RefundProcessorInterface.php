<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Models\Order;
use App\Models\Refund;

interface RefundProcessorInterface
{
    /**
     * Execute the financial reversal.
     * - For Digipay: called OUTSIDE the DB transaction. Throws RefundGatewayException on failure.
     * - For Wallet: called INSIDE the DB transaction (it is itself a DB write).
     * - For Manual: no-op. Returns null.
     *
     * Returns a gateway tracking code, or null.
     */
    public function process(Refund $refund, Order $order, int $amount): ?string;
}
