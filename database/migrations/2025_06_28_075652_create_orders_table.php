<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('increment_id')->unique();

            // --- Statuses ---
            $table->string('status')->comment('Tracks fulfillment (e.g., pending, completed).');

            // --- Customer Details ---
            $table->foreignId('customer_id')->constrained('users');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('customer_first_name');
            $table->string('customer_last_name');
            $table->jsonb('customer_snapshot_json');

            // --- Aggregate Counts ---
            $table->unsignedInteger('total_item_count')->comment('Total number of line items.');
            $table->unsignedInteger('total_qty_ordered')->comment('Total quantity of all items ordered.');

            // --- Core Financials (in IRR) ---
            $table->unsignedBigInteger('subtotal')->comment('Order value before discounts and taxes.');
            $table->unsignedBigInteger('discount_amount')->default(0)->comment('Cart-level discount.');
            $table->unsignedBigInteger('tax_amount')->default(0)->comment('Cart-level tax.');
            $table->unsignedBigInteger('grand_total')->comment('The final, total value of the order.');
            $table->unsignedBigInteger('full_value_grand_total')->default(0)->comment('calculated as if all items were full-payment.');

            // --- Metadata ---
            $table->string('currency_code')->default('IRR');
            $table->string('applied_coupon_code')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff','id')->nullOnDelete();

            $table->timestamps();

            $table->index(['customer_id', 'status'], 'idx_orders_customer_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
