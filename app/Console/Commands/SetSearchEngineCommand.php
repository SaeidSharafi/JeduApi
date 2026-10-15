<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\System\SearchEngine;
use App\Services\SearchEngineService;
use Illuminate\Console\Command;

/**
 * Sets the full-text search engine a deployment uses and makes the database match it.
 *
 * The engine and the five PGroonga indexes are one decision, so they move together:
 * indexes without an engine that uses them cost every request ~200 ms, and an engine
 * without its indexes produces `pgroonga_score()` errors. Doing both in one command is
 * what stops those two states from ever existing.
 */
final class SetSearchEngineCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pgroonga:engine {engine : The search engine this deployment uses (pgroonga or typesense)}';

    /**
     * @var string
     */
    protected $description = 'Choose the deployment search engine and create or drop the PGroonga indexes to match';

    public function __construct(private readonly SearchEngineService $engine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $requested = (string) $this->argument('engine');
        $engine    = SearchEngine::tryFrom($requested);

        if ($engine === null) {
            $this->error(sprintf(
                'Unknown search engine [%s]. Available engines: %s.',
                $requested,
                implode(', ', SearchEngine::getAllValues()),
            ));

            return Command::FAILURE;
        }

        if ($engine === SearchEngine::PGROONGA && $this->engine->extensionVersion() === null) {
            $this->error(
                'Cannot use the pgroonga engine: the PGroonga extension is not installed in this database.'
            );

            return Command::FAILURE;
        }

        if ($engine === SearchEngine::TYPESENSE && ! $this->engine->typesenseIsConfigured()) {
            $this->warn(
                'Typesense has no configured connection; searches will fall back to the database engine.'
            );
        }

        // The database moves first and the stored setting last, in both directions, so a
        // failure part-way leaves the deployment on the side that still works: indexes
        // without a pgroonga engine are merely unused, while an engine without its
        // indexes makes `pgroonga_score()` error.
        if ($engine === SearchEngine::PGROONGA) {
            $this->engine->createIndexes();
            $this->engine->setEngine($engine);
            $this->info(sprintf('Search engine set to [%s] and %d PGroonga indexes ensured.', $engine->value, count($this->engine->indexes())));

            return Command::SUCCESS;
        }

        $this->engine->dropIndexes();
        $this->engine->setEngine($engine);
        $this->info(sprintf('Search engine set to [%s] and PGroonga indexes dropped.', $engine->value));

        return Command::SUCCESS;
    }
}
