<?php

declare(strict_types=1);

namespace App\Console\Commands\Discounts;

use App\Services\Discounts\DiscountHandlerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class ClearHandlerCache extends Command
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
