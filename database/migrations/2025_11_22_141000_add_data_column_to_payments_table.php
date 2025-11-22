<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'data')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('data')->nullable()->after('admin_notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'data')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('data');
            });
        }
    }
};
