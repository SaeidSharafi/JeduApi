<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reward intents decided while pricing the cart are snapshotted onto the
     * order at creation, mirroring orders.applied_cart_discounts_json. The
     * wallet credit itself is granted when the order reaches COMPLETED.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('reward_intents_json')->nullable()->after('applied_cart_discounts_json');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reward_intents_json');
        });
    }
};
