<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->index();
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->unsignedBigInteger('order_item_id');
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('product_delivery_option_id');
            $table->foreign('product_delivery_option_id', 'pdo_id_foreign')->references('id')->on('product_delivery_options')
                ->onDelete('cascade');
            $table->enum('enrollment_status', App\Enums\EnrolmentStatusEnum::getAllValues())
                ->default(App\Enums\EnrolmentStatusEnum::PENDING_PROVISIONING->value);
            $table->date('access_start_date')->nullable();
            $table->date('access_end_date')->nullable();
            $table->unsignedBigInteger('external_enrollment_id')->nullable();
            $table->jsonb('provisioning_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolments');
    }
};
