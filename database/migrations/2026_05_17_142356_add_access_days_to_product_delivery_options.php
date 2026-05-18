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
            if (! Schema::hasColumn('product_delivery_options', 'access_days')) {
                $table->tinyInteger('access_days')->nullable();
            }
        });
    }
};
