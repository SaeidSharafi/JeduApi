<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Refund;
use App\Services\Payment\Refund\ManualRefundProcessor;
use Illuminate\Support\Facades\Log;

it('returns null - no gateway processing for manual refunds', function (): void {
    Log::spy();

    $order  = Order::factory()->withCalculatedTotals([['total' => 100000]])->create();
    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'order_item_id' => $order->items->first()->id,
    ]);
    $processor    = resolve(ManualRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 100000);

    expect($trackingCode)->toBeNull();
    Log::shouldHaveReceived('info')
        ->withArgs(fn ($msg) => str_contains($msg, 'Manual Refund'));
});
