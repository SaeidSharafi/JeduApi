<?php

declare(strict_types=1);

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
        Schema::create('wallet_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name')
                ->comment('Campaign name for admin reference');
            $table->text('description')
                ->nullable()
                ->comment('Campaign details and notes');
            $table->string('type', 50)
                ->comment('Type: registration_bonus, birthday_gift, referral_bonus, etc.');
            $table->boolean('is_active')
                ->default(true)
                ->comment('Master switch for campaign');

            // Amount and restrictions
            $table->unsignedBigInteger('amount')
                ->comment('Gift amount in rials');
            $table->unsignedInteger('usage_limit_total')
                ->nullable()
                ->comment('Total campaign usage limit');
            $table->unsignedInteger('usage_limit_per_user')
                ->nullable()
                ->default(1)
                ->comment('Per-user usage limit');
            $table->unsignedInteger('total_usage_count')
                ->default(0)
                ->comment('Current usage count');

            // Scheduling
            $table->timestamp('starts_at')
                ->nullable()
                ->comment('Campaign start date');
            $table->timestamp('ends_at')
                ->nullable()
                ->comment('Campaign end date');

            // Configuration
            $table->jsonb('metadata')
                ->nullable()
                ->comment('Campaign-specific configuration');

            // Audit
            $table->foreignId('created_by')
                ->nullable()
                ->comment('Admin who created campaign')
                ->constrained('staff', 'id')
                ->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'starts_at', 'ends_at'], 'idx_campaign_active_dates');
            $table->index(['type', 'is_active'], 'idx_campaign_type_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_campaigns');
    }
};
