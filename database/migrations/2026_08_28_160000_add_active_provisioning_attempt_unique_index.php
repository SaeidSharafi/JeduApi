<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX provisioning_attempts_active_unique ON provisioning_attempts (enrollment_id, provider) WHERE status IN ('queued', 'running', 'retry_scheduled')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS provisioning_attempts_active_unique');
    }
};
