<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Install the PGroonga extension.
     *
     * Deliberately the only schema object this migration owns. The extension costs
     * nothing on its own — nothing loads the library unless an index or a query
     * references it — and keeping it installed is what makes switching a deployment's
     * search engine to `pgroonga` a command rather than a reinstall.
     *
     * The five PGroonga indexes are likewise not created by any migration: their
     * existence is the deployment's engine decision, reconciled by `pgroonga:engine`.
     * An index left behind on a deployment that does not search with PGroonga makes the
     * planner load the library on every connection, whether or not that deployment ever
     * searches — which is the entire cost this split exists to avoid.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('CREATE EXTENSION IF NOT EXISTS pgroonga;');
        }
    }
};
