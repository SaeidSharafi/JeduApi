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
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->string('purpose', 50)->default('order')->after('order_id');
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method')->comment('e.g., mellat_gateway, bank_transfer, admin_credit');
            $table->string('status')->index()
                ->default(App\Enums\Payment\PaymentStatusEnum::PENDING->value)
                ->comment('pending, completed, failed');
            $table->string('last_gateway_reference')->nullable()
                ->comment('Last gateway reference ID (e.g., Mellat SaleReferenceId for bank lookups)');
            $table->integer('attempt_count')->default(1)
                ->comment('Number of payment attempts made');
            $table->timestamp('last_attempted_at')->nullable()
                ->comment('Timestamp of the most recent payment attempt');
            $table->string('ip_address')->nullable()
                ->comment('Customer IP address at payment initiation');
            $table->string('user_agent')->nullable()
                ->comment('Customer user agent string at payment initiation');
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
