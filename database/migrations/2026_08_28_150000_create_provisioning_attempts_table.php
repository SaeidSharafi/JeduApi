<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('trigger');
            $table->string('status')->index();
            $table->unsignedInteger('sequence');
            $table->boolean('retryable')->default(true);
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('failure_metadata')->nullable();
            $table->uuid('correlation_id')->index();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('running_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('retry_scheduled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('manual_action_required_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'provider', 'sequence']);
            $table->index(['enrollment_id', 'provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_attempts');
    }
};
