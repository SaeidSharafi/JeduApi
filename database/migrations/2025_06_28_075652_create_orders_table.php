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
            $table->enum('status', OrderStatusEnum::getAllValues())->default(OrderStatusEnum::PENDING->value);
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('customer_first_name');
            $table->string('customer_last_name');
            $table->jsonb('customer_snapshot_json');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('grand_total');
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
