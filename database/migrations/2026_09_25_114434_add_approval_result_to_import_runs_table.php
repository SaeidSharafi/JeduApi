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
        Schema::table('import_runs', function (Blueprint $table): void {
            $table->string('file_checksum', 64)->nullable()->after('file_size');
            $table->unsignedInteger('created_count')->default(0)->after('rows_invalid');
            $table->unsignedInteger('updated_count')->default(0)->after('created_count');
            $table->unsignedInteger('provider_queued_count')->default(0)->after('updated_count');
            $table->timestamp('approved_at')->nullable()->after('provider_queued_count');
        });

        Schema::table('import_run_rows', function (Blueprint $table): void {
            $table->string('target_resource_id')->nullable()->after('identity_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table): void {
            $table->dropColumn('target_resource_id');
        });

        Schema::table('import_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'file_checksum',
                'created_count',
                'updated_count',
                'provider_queued_count',
                'approved_at',
            ]);
        });
    }
};
