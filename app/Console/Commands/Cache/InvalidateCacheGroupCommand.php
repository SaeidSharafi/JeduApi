<?php

declare(strict_types=1);

namespace App\Console\Commands\Cache;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheTag;
use Illuminate\Console\Command;

final class InvalidateCacheGroupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:invalidate {group : The cache group to invalidate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invalidate one cache group by bumping its version counter';

    public function __construct(private readonly CacheStore $cache)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $group = (string) $this->argument('group');
        $tag   = CacheTag::tryFrom($group);

        if ($tag === null) {
            $this->error(sprintf(
                'Unknown cache group [%s]. Available groups: %s.',
                $group,
                implode(', ', CacheTag::values()),
            ));

            return Command::FAILURE;
        }

        $this->cache->invalidate($tag);

        $this->info(sprintf(
            'Cache group [%s] invalidated; its version is now %d.',
            $tag->value,
            $this->cache->version($tag),
        ));

        return Command::SUCCESS;
    }
}
