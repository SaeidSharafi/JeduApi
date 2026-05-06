<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_delivery_options', function (Blueprint $table) {
            // Composite index used by nearingCapacity() filter and sortByCapacityUtilization().
            // Both query paths filter/sort on: product_id, status, capacity, enrolled_count.
            // Covering index avoids heap fetches for the correlated subqueries.
            $table->index(
                ['product_id', 'status', 'capacity', 'enrolled_count'],
                'idx_pdo_capacity_utilization'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_delivery_options', function (Blueprint $table) {
            $table->dropIndex('idx_pdo_capacity_utilization');
        });
    }
};
