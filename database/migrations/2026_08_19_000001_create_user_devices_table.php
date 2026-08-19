<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the server-side device fingerprint registry.
     *
     * Each row records a single registration event (the row itself is the
     * velocity counter). device_hash is sha256(ip + user_agent) hex and is
     * used, together with ip_address, to enforce the daily registration caps.
     */
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('device_hash', 64)->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
