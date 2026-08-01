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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_published_delivery_option')->default(false)->after('is_featured')->index();
            $table->string('productable_status')->default('draft')->after('has_published_delivery_option')->index();
            $table->boolean('is_term_active')->default(true)->after('productable_status')->index();
            $table->date('earliest_registration_start')->nullable()->after('is_term_active');
            $table->date('latest_registration_end')->nullable()->after('earliest_registration_start');
            $table->date('earliest_availability_start')->nullable()->after('latest_registration_end');
            $table->date('latest_availability_end')->nullable()->after('earliest_availability_start');
            $table->boolean('near_capacity')->default(false)->after('latest_availability_end')->index();
            $table->decimal('max_capacity_utilization', 5, 2)->default(0)->after('near_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'has_published_delivery_option',
                'productable_status',
                'is_term_active',
                'earliest_registration_start',
                'latest_registration_end',
                'earliest_availability_start',
                'latest_availability_end',
                'near_capacity',
                'max_capacity_utilization',
            ]);
        });
    }
};
