<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method')->comment('e.g., online_gateway, bank_transfer, admin_credit');
            $table->string('status')->index()
                ->default(App\Enums\Payment\PaymentStatusEnum::PENDING->value)
                ->comment('pending, completed, failed');
            $table->jsonb('data')->nullable()
                ->comment('Additional data related to the payment, e.g., transaction ID, gateway response, etc.');
            $table->text('admin_notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->comment('The admin who initiated the payment (if is created by admin).')
                ->constrained('staff', 'id')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
