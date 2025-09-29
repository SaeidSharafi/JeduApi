<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateProductPricingJob;
use App\Models\ProductDeliveryOption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class CheckExpiredFeaturedPricesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'prices:check-expired-featured
                           {--dry-run : Show what would be updated without making changes}
                           {--queue=default : The queue to dispatch jobs to}';

    /**
     * The console command description.
     */
    protected $description = 'Check for expired featured prices and update price index accordingly';

    public function handle(): int
    {
        $lock = Cache::lock('price-indexing', 60); // 1 minute lock

        if (! $lock->get()) {
            $this->warn('Another price indexing operation is already running. Skipping expired price check.');

            return Command::SUCCESS;
        }

        try {
            $this->info('Checking for expired featured prices...');

            // Find delivery options with expired featured prices
            $expiredOptions = ProductDeliveryOption::where('is_featured', true)
                ->whereNotNull('featured_price_end_date')
                ->where('featured_price_end_date', '<=', now())
                ->with('product:id,name')
                ->get();

            if ($expiredOptions->isEmpty()) {
                $this->info('No expired featured prices found.');

                return Command::SUCCESS;
            }

            $this->info("Found {$expiredOptions->count()} expired featured prices.");

            if ($this->option('dry-run')) {
                $this->warn('DRY RUN MODE - No changes will be made');
                $this->table(
                    ['Product ID', 'Product Name', 'Option ID', 'Expired Date'],
                    $expiredOptions->map(fn ($option) => [
                        $option->product_id,
                        $option->product->name ?? 'Unknown',
                        $option->id,
                        $option->featured_price_end_date->format('Y-m-d H:i:s'),
                    ])
                );

                return Command::SUCCESS;
            }

            // Get unique product IDs to update
            $productIds = $expiredOptions->pluck('product_id')->unique();

            // Dispatch job to update price index for affected products
            UpdateProductPricingJob::dispatch($productIds->toArray())
                ->onQueue($this->option('queue'));

            $this->info("Dispatched price index update job for {$productIds->count()} products.");
            $this->info('Featured price expiration check completed successfully.');

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
