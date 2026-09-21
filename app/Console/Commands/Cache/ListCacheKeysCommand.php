<?php

declare(strict_types=1);

namespace App\Console\Commands\Cache;

use App\Enums\System\CacheKey;
use Illuminate\Console\Command;

final class ListCacheKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List every cache key in the registry with its durations and invalidation group';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->table(
            ['Key', 'Template', 'TTL', 'Stale TTL', 'Group'],
            array_map(fn (CacheKey $key): array => [
                $key->name,
                $key->value,
                $key->ttl()      ?? 'forever',
                $key->staleTtl() ?? 'none',
                $key->group()->value,
            ], CacheKey::cases()),
        );

        return Command::SUCCESS;
    }
}
