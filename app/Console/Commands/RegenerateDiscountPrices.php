<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateProductPricingJob;
use App\Services\Discounts\ProductDiscountIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class RegenerateDiscountPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discounts:reindex-all {--skip-price-index : Skip updating the price index table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate the product_delivery_option_discount_prices table for all active promotions and update price index.';

    public function handle(ProductDiscountIndexer $indexer): int
    {
        $this->info('Regenerating discount prices...');
        $indexer->reIndexComplete();
        $this->info('Discount prices regenerated successfully.');

        DB::afterCommit(function (){
            if (! $this->option('skip-price-index')) {
                $this->info('Updating price index for all products...');

                Artisan::call('prices:index-all --sync');
                $this->info('Price index update jobs dispatched successfully.');
            }

        });

        return 0;
    }
}
