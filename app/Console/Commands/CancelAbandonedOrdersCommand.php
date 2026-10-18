<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Order\CancelOrderAction;
use App\Actions\Payment\FailPendingOrderPaymentsAction;
use App\Enums\Order\OrderCancellationReasonEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Models\Order;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class CancelAbandonedOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-abandoned
                           {--timeout= : Minutes of inactivity after which a pending order is considered abandoned (defaults to config order.abandonment_minutes)}
                           {--dry-run : Show what would be cancelled without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel pending orders that have been inactive for the whole abandonment window';

    public function __construct(
        private CancelOrderAction $cancelOrder,
        private FailPendingOrderPaymentsAction $failPendingOrderPayments,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeout   = (int) ($this->option('timeout') ?? config('order.abandonment_minutes'));
        $dryRun    = $this->option('dry-run');
        $threshold = now()->subMinutes($timeout);

        $this->info("Checking for abandoned orders inactive for {$timeout} minutes (before {$threshold})...");

        $abandonedOrders = $this->abandonedOrdersQuery($threshold);
        $count           = $abandonedOrders->count();

        if ($count === 0) {
            $this->info('No abandoned orders found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$count} abandoned order(s).");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->table(
                ['Order ID', 'Increment ID', 'Customer', 'Created At', 'Grand Total'],
                $abandonedOrders
                    ->get()
                    ->map(fn (Order $o): array => [
                        $o->id,
                        $o->increment_id,
                        $o->customer_email,
                        $o->created_at->toDateTimeString(),
                        number_format($o->grand_total).' IRR',
                    ])
            );

            return Command::SUCCESS;
        }

        $cancelledCount = 0;

        $this->abandonedOrdersQuery($threshold)->chunkById(
            100,
            function ($orders) use ($threshold, &$cancelledCount): void {
                foreach ($orders as $order) {
                    try {
                        if (! $this->cancelIfStillAbandoned($order, $threshold)) {
                            continue;
                        }

                        $this->info("✓ Cancelled order #{$order->increment_id} (ID: {$order->id})");
                        $cancelledCount++;
                    }
                    // @codeCoverageIgnoreStart
                    catch (Exception $e) {
                        $this->error("✗ Failed to cancel order #{$order->increment_id}: ".$e->getMessage());
                    }
                    // @codeCoverageIgnoreEnd
                }
            }
        );

        $this->info("Successfully cancelled {$cancelledCount} out of {$count} abandoned order(s).");

        return Command::SUCCESS;
    }

    /**
     * Pending orders with no activity for the whole window and no completed payment.
     *
     * Every checkout attempt creates a payment row before the processor runs, so a
     * "no payments at all" filter would never match a real abandoned checkout. The
     * retry window is therefore measured from the most recent activity: order
     * creation/update, payment creation, or the payment's last attempt. Orders with
     * a completed payment are never eligible, even if their status is still pending.
     */
    /**
     * @return Builder<Order>
     */
    private function abandonedOrdersQuery(CarbonInterface $threshold): Builder
    {
        return Order::query()
            ->where('status', OrderStatusEnum::PENDING)
            ->where('created_at', '<', $threshold)
            ->where('updated_at', '<', $threshold)
            ->whereDoesntHave('payments', fn (Builder $query): Builder => $this->blockingPaymentScope($query, $threshold));
    }

    /**
     * A payment that blocks cancellation: it settled the order, or it was
     * touched/attempted inside the inactivity window (the user may still retry).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function blockingPaymentScope(Builder $query, CarbonInterface $threshold): Builder
    {
        return $query
            ->where('status', PaymentStatusEnum::COMPLETED->value)
            ->orWhere('created_at', '>=', $threshold)
            ->orWhere('last_attempted_at', '>=', $threshold);
    }

    /**
     * Cancel the order only if it is still abandoned when the row is locked.
     *
     * The eligibility query and the cancellation are not atomic: a customer can
     * start a retry between them. Locking the order and re-checking payments
     * inside the transaction prevents cancelling an order that just gained a
     * fresh payment attempt.
     */
    private function cancelIfStillAbandoned(Order $order, CarbonInterface $threshold): bool
    {
        $cancelled = DB::transaction(function () use ($order, $threshold): bool {
            $locked = Order::query()
                ->with('items.enrollment')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status !== OrderStatusEnum::PENDING) {
                return false;
            }

            if ($locked->payments()
                ->where(fn (Builder $query): Builder => $this->blockingPaymentScope($query, $threshold))
                ->exists()) {
                return false;
            }

            $this->cancelOrder->handle($locked, OrderCancellationReasonEnum::ABANDONED);

            return true;
        });

        if ($cancelled) {
            // The order transaction has committed, so closing its payment
            // attempts here cannot deadlock with a gateway verification.
            $this->failPendingOrderPayments->handle($order->id);
        }

        return $cancelled;
    }
}
