<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Discounts\ProductDeliveryOptionDiscountPriceRegenerator;

class RegenerateDiscountPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discounts:regenerate-discount-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate the product_delivery_option_discount_prices table for all active promotions.';

    public function handle(ProductDeliveryOptionDiscountPriceRegenerator $regenerator): int
    {
        $this->info('Regenerating discount prices...');
        $regenerator->regenerate();
        $this->info('Discount prices regenerated successfully.');
        return 0;
    }
}
