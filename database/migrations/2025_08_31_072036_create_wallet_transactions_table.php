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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete()
                ->comment('Reference to the wallet');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('Reference to the user (for quick queries)');
            $table->string('type')
                ->comment('Transaction type: deposit, withdrawal, payment, refund, gift, bonus, adjustment');
            $table->bigInteger('amount')
                ->comment('Positive for credits, negative for debits (in rials)');
            $table->unsignedBigInteger('balance_after')
                ->comment('Snapshot of total balance after transaction');
            $table->unsignedBigInteger('gift_balance_after')
                ->comment('Snapshot of gift balance after transaction');
            $table->string('source_type')
                ->comment('Source type: order, admin, promotion, refund, manual, system');
            $table->unsignedBigInteger('source_id')
                ->nullable()
                ->comment('ID of the source record');
            $table->text('description')
                ->nullable()
                ->comment('Human-readable description of the transaction');
            $table->jsonb('metadata')
                ->nullable()
                ->comment('Flexible data storage for additional transaction details');
            $table->timestamp('expires_at')
                ->nullable()
                ->comment('Expiry date for promotional credits');
            $table->foreignId('created_by')
                ->index()
                ->nullable()
                ->comment('The admin who created the transaction (if created manually)')
                ->constrained('staff', 'id')
                ->nullOnDelete();
            $table->timestamps();

            // Indexes for performance
            $table->index('wallet_id');
            $table->index('user_id');
            $table->index('type');
            $table->index(['source_type', 'source_id']);
            $table->index('created_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
