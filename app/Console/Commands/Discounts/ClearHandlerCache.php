<?php

namespace App\Console\Commands\Discounts;

use Illuminate\Console\Command;

class ClearHandlerCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discounts:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the cached discount handler registry';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Clearing discount handler cache...');

        Cache::forget(DiscountHandlerRegistry::CACHE_KEY);

        $this->info('Discount handler cache cleared successfully.');
    }
}
