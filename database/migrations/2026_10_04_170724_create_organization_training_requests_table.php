<?php

declare(strict_types=1);

use App\Enums\InboundRequestStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_training_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 20);
            $table->string('position');
            $table->string('organization_name');
            $table->json('requested_course_names');
            $table->text('notes')->nullable();
            $table->string('status')->index()->default(InboundRequestStatusEnum::PENDING->value);
            $table->foreignId('assigned_to_id')->nullable()->index()->constrained('staff')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->index()->constrained('vendors')->restrictOnDelete();
            $table->json('vendor_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_training_requests');
    }
};
