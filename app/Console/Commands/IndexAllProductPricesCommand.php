<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Content\PublicationStatusEnum;
use App\Jobs\UpdateProductPricingJob;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class IndexAllProductPricesCommand extends Command
{
    protected $signature = 'prices:index-all
                           {--queue=default : The queue to dispatch jobs to}
                           {--sync : Run jobs synchronously}
                           {--missing-only : Only process products missing price index entries}';

    protected $description = 'Re-calculates and caches the price data for products. Can be used for initial setup (--missing-only) or full re-indexing.';

    public function handle(): int
    {
        $lock = Cache::lock('price-indexing', 1800 );

        if (! $lock->get()) {
            $this->warn('Another price indexing operation is already running. Skipping.');

            return Command::SUCCESS;
        }

        try {
            $missingOnly = $this->option('missing-only');

            $this->info('Starting product price indexing...');

            $query = Product::where('status', PublicationStatusEnum::PUBLISHED);

            // Apply missing-only filter if requested
            if ($missingOnly) {
                $query->whereDoesntHave('productPrice');
                $this->info('Filtering to products missing price index entries...');
            }

            // Get the total count for the progress bar
            $totalProducts = $query->count();

            if ($totalProducts === 0) {
                $message = $missingOnly
                    ? 'No products found missing price index entries.'
                    : 'No published products found to index.';
                $this->warn($message);

                return Command::SUCCESS;
            }

            $this->info("Found {$totalProducts} products to process.");
            $progressBar = $this->output->createProgressBar($totalProducts);
            $progressBar->start();

            $this->info("\nDispatching pricing update jobs...");
            $this->dispatchPricingJobs($query, $progressBar);

            $progressBar->finish();
            $this->info("\nSuccessfully dispatched price indexing jobs for {$totalProducts} products.");

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function dispatchPricingJobs(object $query, object $progressBar): void
    {
        $query->select('id')->chunkById(200, function (Collection $products) use ($progressBar): void {
            $productIds = $products->pluck('id')->all();

            if ($this->option('sync')) {
                UpdateProductPricingJob::dispatchSync($productIds);
            } else {
                UpdateProductPricingJob::dispatch($productIds)
                    ->onQueue($this->option('queue'));
            }

            $progressBar->advance($products->count());
        });
    }
}
