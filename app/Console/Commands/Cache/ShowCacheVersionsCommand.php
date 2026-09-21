<?php

declare(strict_types=1);

namespace App\Console\Commands\Cache;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use Illuminate\Console\Command;

final class ShowCacheVersionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:versions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the current invalidation version of every cache group';

    public function __construct(private readonly CacheStore $cache)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->table(
            ['Group', 'Version'],
            array_map(fn (CacheTag $tag): array => [
                $tag->value,
                $this->cache->version($tag),
            ], CacheTag::cases()),
        );

        return Command::SUCCESS;
    }
}
