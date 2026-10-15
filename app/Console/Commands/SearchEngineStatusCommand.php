<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\System\SearchEngine;
use App\Services\SearchEngineService;
use Illuminate\Console\Command;

/**
 * Reports the deployment's search engine and whether the database actually matches it.
 *
 * The failure this exists to make visible is drift: an engine chosen but never applied
 * to the database, or a database that drifted from the engine. It also distinguishes
 * "no engine chosen" from "the engine is not PGroonga", because a fresh migration
 * removes the setting and that is not a valid choice.
 */
final class SearchEngineStatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pgroonga:status';

    /**
     * @var string
     */
    protected $description = 'Show the chosen search engine, whether the PGroonga indexes match it, and the extension version';

    public function __construct(private readonly SearchEngineService $engine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $engine   = $this->engine->engine();
        $existing = $this->engine->existingIndexes();
        $expected = count($this->engine->indexes());
        $found    = count($existing);
        $version  = $this->engine->extensionVersion();

        $this->table(['Setting', 'Value'], [
            ['Engine', $engine->value],
            ['PGroonga extension', $version ?? 'not installed'],
            ['PGroonga indexes', sprintf('%d of %d', $found, $expected)],
        ]);

        if ($engine === SearchEngine::PGROONGA && $found < $expected) {
            $this->error(sprintf(
                'The engine is pgroonga but %d of %d indexes are missing. Run `pgroonga:engine pgroonga` to create them.',
                $expected - $found,
                $expected,
            ));

            return Command::FAILURE;
        }

        if ($engine === SearchEngine::TYPESENSE && $found > 0) {
            $this->error(sprintf(
                'The engine is typesense but %d PGroonga indexes still exist, costing every request the library load. Run `pgroonga:engine typesense` to drop them.',
                $found,
            ));

            return Command::FAILURE;
        }

        $this->info(sprintf('The database matches the chosen engine [%s].', $engine->value));

        return Command::SUCCESS;
    }
}
