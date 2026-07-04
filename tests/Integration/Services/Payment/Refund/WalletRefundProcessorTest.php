<?php

declare(strict_types=1);

use App\Actions\Wallet\RecordWalletTransactionAction;
use App\Data\Admin\Wallet\RecordTransactionData;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Enums\Wallet\TransactionTypeEnum;
use App\Models\Order;
use App\Models\Refund;
use App\Models\WalletTransaction;
use App\Services\Payment\Refund\WalletRefundProcessor;
use Mockery\MockInterface;

it('processes wallet refund by recording a wallet transaction', function (): void {
    // Arrange
    $order  = Order::factory()->withCalculatedTotals([['total' => 250000]])->create();
    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 250000,
    ]);

    $this->mock(RecordWalletTransactionAction::class, function (MockInterface $mock) use ($order, $refund): void {
        $mock->shouldReceive('execute')
            ->once()
            ->withArgs(function (RecordTransactionData $data) use ($order, $refund) {
                return $data->user_id     === $order->customer_id
                    && $data->amount      === 250000
                    && $data->type        === TransactionTypeEnum::REFUND
                    && $data->source_type === TransactionSourceEnum::ORDER
                    && $data->source_id   === $refund->id
                    && str_contains($data->description, "order #{$order->id}");
            })
            ->andReturn(WalletTransaction::factory()->make());
    });

    // Act
    $processor    = resolve(WalletRefundProcessor::class);
    $trackingCode = $processor->process($refund, $order, 250000);

    // Assert
    expect($trackingCode)->toBeNull(); // Wallet refunds return no gateway tracking code
});

it('creates refund transaction with correct source linkage', function (): void {
    $order  = Order::factory()->withCalculatedTotals([['total' => 100000]])->create();
    $refund = Refund::factory()->create([
        'order_id'      => $order->id,
        'order_item_id' => $order->items->first()->id,
        'amount'        => 100000,
    ]);

    $capturedData = null;
    $this->mock(RecordWalletTransactionAction::class, function (MockInterface $mock) use (&$capturedData): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturnUsing(function (RecordTransactionData $data) use (&$capturedData) {
                $capturedData = $data;

                return WalletTransaction::factory()->make();
            });
    });

    $processor = resolve(WalletRefundProcessor::class);
    $processor->process($refund, $order, 100000);

    expect($capturedData->source_type)->toBe(TransactionSourceEnum::ORDER)
        ->and($capturedData->source_id)->toBe($refund->id)
        ->and($capturedData->type)->toBe(TransactionTypeEnum::REFUND);
});
