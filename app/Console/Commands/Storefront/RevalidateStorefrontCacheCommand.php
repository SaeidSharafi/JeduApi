<?php

declare(strict_types=1);

namespace App\Console\Commands\Storefront;

use App\Enums\System\StorefrontTag;
use App\Services\Storefront\StorefrontRevalidationService;
use Illuminate\Console\Command;

/**
 * Operator escape hatch for the storefront cache webhook.
 *
 * The webhook is driven by write paths; this command exists for the cases no
 * write path covers: right after a deploy, when a tag drifted and the storefront
 * logged a rejection, or when someone suspects the storefront is stale and does
 * not want to wait for its TTL.
 */
final class RevalidateStorefrontCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storefront:revalidate
                            {--tag=* : Storefront tag to clear; repeatable}
                            {--all : Clear every storefront tag}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear storefront cache tags through the revalidation webhook';

    public function __construct(private readonly StorefrontRevalidationService $revalidation)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var list<string> $requested */
        $requested = (array) $this->option('tag');
        $all       = (bool) $this->option('all');

        if ($all) {
            $tags = StorefrontTag::values();
        } else {
            $tags = array_map(static fn (string $tag): string => mb_trim($tag), $requested);
        }

        if ($tags === []) {
            $this->error(sprintf(
                'Nothing to revalidate. Pass --tag=<tag> (repeatable) or --all. Available tags: %s.',
                implode(', ', StorefrontTag::values()),
            ));

            return Command::FAILURE;
        }

        $unknown = array_values(array_filter(
            $tags,
            static fn (string $tag): bool => StorefrontTag::tryFrom($tag) === null,
        ));

        if ($unknown !== []) {
            $this->error(sprintf(
                'Unknown storefront tag(s) [%s]. Available tags: %s.',
                implode(', ', $unknown),
                implode(', ', StorefrontTag::values()),
            ));

            return Command::FAILURE;
        }

        if (! $this->revalidation->isConfigured()) {
            $this->error('The storefront webhook is not configured (STOREFRONT_URL / STOREFRONT_REVALIDATE_SECRET); nothing was sent.');

            return Command::FAILURE;
        }

        // Synchronous, and through the service rather than the job: an operator running
        // this wants the storefront's answer and its exit code, not a queue entry. It is
        // the same HTTP call the queued job makes.
        $cleared = $this->revalidation->revalidate(array_values(array_unique($tags)));

        if (! $cleared) {
            $this->error('The storefront refused the revalidation; its error and rejected tags are in the log.');

            return Command::FAILURE;
        }

        $this->info(sprintf('Revalidated %d storefront tag(s): %s.', count($tags), implode(', ', $tags)));

        return Command::SUCCESS;
    }
}
