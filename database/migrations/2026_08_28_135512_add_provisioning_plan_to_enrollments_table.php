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
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->jsonb('provisioning_plan')
                ->default('{"version":1,"providers":[],"status":"healthy"}')
                ->after('provisioning_data');
            $table->string('provisioning_status')->default('healthy')->index()->after('provisioning_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn(['provisioning_plan', 'provisioning_status']);
        });
    }
};
