<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->jsonb('providers')->nullable();
            $table->string('local_resource_id')->nullable();
            $table->jsonb('local_result_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table) {
            $table->dropColumn(['providers', 'local_resource_id', 'local_result_data']);
        });
    }
};
