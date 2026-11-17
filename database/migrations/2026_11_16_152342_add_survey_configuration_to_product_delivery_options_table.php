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
            $table->boolean('survey_required')->default(false);
            $table->text('survey_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_delivery_options', function (Blueprint $table) {
            $table->dropColumn(['survey_required', 'survey_url']);
        });
    }
};
