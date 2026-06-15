<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dateTime('event_start_at')->nullable();
            $table->dateTime('event_ended_at')->nullable();

            $table->index('event_start_at');
            $table->index('event_ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['event_start_at']);
            $table->dropIndex(['event_ended_at']);
            $table->dropColumn(['event_start_at', 'event_ended_at']);
        });
    }
};
