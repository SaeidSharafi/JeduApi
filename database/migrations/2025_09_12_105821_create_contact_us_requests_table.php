<?php

declare(strict_types=1);

use App\Enums\InboundRequestStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_us_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('subject');
            $table->string('email')->nullable();
            $table->text('message');
            $table->string('status')->index()->default(InboundRequestStatusEnum::PENDING->value);
            $table->text('note')->nullable();
            $table->foreignId('assigned_to_id')->nullable()->index();
            $table->foreign('assigned_to_id')->references('id')->on('staff')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_us_requests');
    }
};
