<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_logs', function (Blueprint $table): void {
            $table->dropForeign('admin_action_logs_admin_id_foreign');
            $table->bigInteger('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_action_logs', function (Blueprint $table): void {
            $table->dropForeign('admin_action_logs_admin_id_foreign');
            $table->bigInteger('admin_id')->nullable(false)->change();
            $table->foreign('admin_id')->references('id')->on('staff')->restrictOnDelete();
        });
    }
};
