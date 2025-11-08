<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->nullable()->unique();
        });

        // Generate UUIDs for existing payments
        DB::table('payments')->whereNull('uuid')->chunkById(100, function ($payments) {
            foreach ($payments as $payment) {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        });

        // Make uuid non-nullable after populating existing records
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
