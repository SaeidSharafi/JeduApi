<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assetables', function (Blueprint $table) {
            $table->foreignId('digital_asset_id')
                ->constrained('digital_assets')
                ->restrictOnDelete();
            $table->unsignedBigInteger('assetable_id');
            $table->string('assetable_type');
            $table->primary(['digital_asset_id', 'assetable_id', 'assetable_type'], 'assetables_primary');
            $table->index(['digital_asset_id', 'assetable_type'], 'assetables_index');
        });
    }
};
