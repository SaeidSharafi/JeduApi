<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateProductAvailabilityJob;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class IndexAllProductAvailabilityCommand extends Command
{
    protected $signature = 'products:index-availability
                            {--queue=default : The queue to dispatch jobs to}
                            {--sync : Run jobs synchronously}';

    protected $description = 'Recompute denormalized availability snapshots for all products';

    public function handle(): int
    {
        $lock = Cache::lock('product-availability-indexing', 3600);

        if (! $lock->get()) {
            $this->warn('Another availability indexing operation is already running. Skipping.');

            return Command::SUCCESS;
        }

        try {
            $query = Product::query();
            $total = $query->count();

            if ($total === 0) {
                $this->warn('No products found to index.');

                return Command::SUCCESS;
            }

            $this->info("Found {$total} products to process.");

            $query->select('id')->chunkById(200, function (Collection $products): void {
                $productIds = $products->pluck('id')->all();

                if ($this->option('sync')) {
                    UpdateProductAvailabilityJob::dispatchSync($productIds);

                    return;
                }

                UpdateProductAvailabilityJob::dispatch($productIds)
                    ->onQueue($this->option('queue'));
            });

            $this->info("Successfully dispatched availability indexing jobs for {$total} products.");

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
