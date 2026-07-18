<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advice_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('status')->index()->default(App\Enums\AdviceRequestStatusEnum::PENDING->value);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('handled_by_id')->nullable()->index();
            $table->foreign('handled_by_id')->references('id')->on('staff')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advice_requests');
    }
};
