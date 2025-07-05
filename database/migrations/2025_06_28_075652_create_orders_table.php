<?php

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('increment_id')->unique();

            // --- Statuses ---
            $table->string('status')->comment('Tracks fulfillment (e.g., pending, completed).');
            $table->string('payment_status')->comment('Tracks financial state (e.g., partially_paid, paid).');

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

            // --- Payment & Balance Tracking ---
            $table->unsignedBigInteger('amount_paid')->default(0)->comment('Total cash received from the customer.');
            $table->unsignedBigInteger('amount_refunded')->default(0)->comment('Total cash returned to the customer.');
            $table->unsignedBigInteger('balance_due')->comment('The remaining amount to be collected (grand_total - amount_paid).');

            // --- Metadata ---
            $table->string('currency_code')->default('IRR');
            $table->string('applied_coupon_code')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
