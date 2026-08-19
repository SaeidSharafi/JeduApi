<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add hard-ban columns to staff.
     *
     * is_banned gates both password and OTP login; banned_at records when
     * the ban was applied. Unbanning clears the flag and the timestamp.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_banned')->default(false)->after('is_admin');
            $table->timestamp('banned_at')->nullable()->after('is_banned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['is_banned', 'banned_at']);
        });
    }
};
