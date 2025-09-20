<?php

namespace App\Console\Commands;

use App\Enums\PublicationStatusEnum;
use App\Jobs\UpdateProductPriceCacheJob;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class IndexAllProductPricesCommand extends Command
{
    protected $signature = 'prices:index-all {--queue=default : The queue to dispatch jobs to} {--sync : Run jobs synchronously}';

    protected $description = 'Re-calculates and caches the price data for ALL published products by dispatching jobs.';

    public function handle(): int
    {
        $this->info('Starting full re-index of all product prices...');

        $query = Product::where('status', PublicationStatusEnum::PUBLISHED);

        // Get the total count for the progress bar
        $totalProducts = $query->count();
        if ($totalProducts === 0) {
            $this->warn('No published products found to index.');
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        // Process in chunks to avoid memory exhaustion on large databases
        $query->select('id')->chunkById(200, function (Collection $products) use ($progressBar): void {
            foreach ($products as $product) {
                if ($this->option('sync')) {
                    // Run the job synchronously
                    UpdateProductPriceCacheJob::dispatchSync($product->id);
                    continue;
                }

                UpdateProductPriceCacheJob::dispatch($product->id)
                    ->onQueue($this->option('queue'));
            }
            $progressBar->advance($products->count());
        });

        $progressBar->finish();
        $this->info("\nSuccessfully dispatched price indexing jobs for {$totalProducts} products.");

        return Command::SUCCESS;
    }
}
