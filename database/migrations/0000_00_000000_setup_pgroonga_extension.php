<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('CREATE EXTENSION IF NOT EXISTS pgroonga;');

            DB::unprepared('
                CREATE OR REPLACE FUNCTION use_pgroonga() RETURNS boolean AS $$
                BEGIN
                    RETURN true;
                END;
                $$ LANGUAGE plpgsql IMMUTABLE;
            ');
        }
    }
};
