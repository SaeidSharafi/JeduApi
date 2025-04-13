<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->morphs('otpable');
            $table->string('purpose', 10);
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['otpable_type', 'otpable_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
