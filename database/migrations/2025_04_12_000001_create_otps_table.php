<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->morphs('otpable'); // This will create otpable_type and otpable_id for polymorphic relation
            $table->string('identifier'); // email or phone
            $table->string('type', 10); // email or phone
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['otpable_type', 'otpable_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
