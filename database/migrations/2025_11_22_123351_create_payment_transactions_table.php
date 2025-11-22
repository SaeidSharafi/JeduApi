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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete()->index();
            $table->string('transaction_reference')->unique()->index()
                ->comment('Human-readable unique reference for customer support (e.g., 200000001)');
            $table->integer('attempt_number')->default(1)
                ->comment('Sequential attempt number for this payment');
            $table->string('status')->default(App\Enums\Payment\PaymentTransactionStatusEnum::INITIATED->value)
                ->comment('initiated, completed, failed');
            $table->jsonb('gateway_request')->nullable()
                ->comment('Request data sent to gateway (SOAP params, API payload, etc.)');
            $table->jsonb('gateway_response')->nullable()
                ->comment('Response data from gateway (callback data, RefId, etc.)');
            $table->timestamp('initiated_at')
                ->comment('When the transaction attempt started');
            $table->timestamp('completed_at')->nullable()
                ->comment('When the transaction finished (success or failure)');
            $table->string('error_code')->nullable()
                ->comment('Gateway error code if transaction failed');
            $table->text('error_message')->nullable()
                ->comment('Human-readable error message');
            $table->string('ip_address')->nullable()
                ->comment('Customer IP address at time of transaction');
            $table->string('user_agent')->nullable()
                ->comment('Customer user agent string');
            $table->timestamps();

            $table->index(['payment_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
