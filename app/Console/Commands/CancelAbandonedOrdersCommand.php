<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CancelAbandonedOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-abandoned
                           {--timeout=30 : Minutes after which a pending order is considered abandoned}
                           {--dry-run : Show what would be cancelled without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel abandoned pending orders that have not received payment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeout   = (int) $this->option('timeout');
        $dryRun    = $this->option('dry-run');
        $threshold = now()->subMinutes($timeout);

        $this->info("Checking for abandoned orders older than {$timeout} minutes (created before {$threshold})...");

        // Find orders that are truly abandoned:
        // 1. Status is PENDING
        // 2. Have NO payment attempts at all (not even failed ones)
        //    This means the user never even tried to pay - they just left after creating the order
        // 3. Created more than timeout minutes ago
        //
        // Note: Orders with failed/pending payment attempts are NOT cancelled - users can retry those
        $abandonedOrders = Order::query()
            ->where('status', OrderStatusEnum::PENDING)
            ->where('created_at', '<', $threshold)
            ->whereDoesntHave('payments') // No payment records at all
            ->with(['items.enrollment', 'customer'])
            ->get();

        if ($abandonedOrders->isEmpty()) {
            $this->info('No abandoned orders found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$abandonedOrders->count()} abandoned order(s).");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->table(
                ['Order ID', 'Increment ID', 'Customer', 'Created At', 'Grand Total'],
                $abandonedOrders->map(fn (Order $o) => [
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

        foreach ($abandonedOrders as $order) {
            try {
                DB::transaction(function () use ($order): void {
                    // Update order status to CANCELLED
                    $order->status = OrderStatusEnum::CANCELLED;
                    $order->save();

                    // Cancel all associated enrollments
                    foreach ($order->items as $item) {
                        if ($item->enrollment && $item->enrollment->enrollment_status === EnrollmentStatusEnum::PENDING_PROVISIONING) {
                            $item->enrollment->enrollment_status = EnrollmentStatusEnum::CANCELLED;
                            $item->enrollment->save();
                        }
                    }

                    // Dispatch event for any listeners
                    OrderStatusUpdatedEvent::dispatch($order);
                });

                $this->info("✓ Cancelled order #{$order->increment_id} (ID: {$order->id})");
                $cancelledCount++;
            }
            // @codeCoverageIgnoreStart
            catch (Exception $e) {
                $this->error("✗ Failed to cancel order #{$order->increment_id}: ".$e->getMessage());
            }
            // @codeCoverageIgnoreEnd
        }

        $this->info("Successfully cancelled {$cancelledCount} out of {$abandonedOrders->count()} abandoned order(s).");

        return Command::SUCCESS;
    }
}
