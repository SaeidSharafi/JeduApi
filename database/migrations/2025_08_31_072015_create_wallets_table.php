<?php

declare(strict_types=1);

use App\Enums\Wallet\WalletStatusEnum;
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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('One wallet per user');
            $table->unsignedBigInteger('balance')
                ->default(0)
                ->comment('Available balance in rials (smallest currency unit)');
            $table->unsignedBigInteger('gift_balance')
                ->default(0)
                ->comment('Non-withdrawable gift amounts in rials');
            $table->string('status')
                ->default(WalletStatusEnum::ACTIVE->value)
                ->comment('Wallet status: active, suspended, closed');
            $table->foreignId('created_by')
                ->nullable()
                ->comment('The admin who created the wallet (if created manually)')
                ->constrained('staff', 'id')
                ->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
