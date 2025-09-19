<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Discounts\ProductDiscountIndexer;
use Illuminate\Console\Command;

final class RegenerateDiscountPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discounts:reindex-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate the product_delivery_option_discount_prices table for all active promotions.';

    public function handle(ProductDiscountIndexer $indexer): int
    {
        $this->info('Regenerating discount prices...');
        $indexer->reIndexComplete();
        $this->info('Discount prices regenerated successfully.');

        return 0;
    }
}
