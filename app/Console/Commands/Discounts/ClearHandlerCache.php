<?php

declare(strict_types=1);

namespace App\Console\Commands\Discounts;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use Illuminate\Console\Command;

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

    public function __construct(private readonly CacheStore $cache)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Clearing discount handler cache...');

        $this->cache->forget(CacheKey::DiscountHandlers);

        $this->info('Discount handler cache cleared successfully.');
    }
}
