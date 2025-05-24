<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Schema\Blueprint;

trait HasMetaTagsMigration
{
    /**
     * Adds meta tag columns to the table.
     */
    protected function addMetaTagColumns(Blueprint $table): void
    {
        $table->string('meta_title', 70)->nullable();
        $table->string('meta_description', 160)->nullable();
        $table->string('meta_keywords', 255)->nullable();
    }
}
