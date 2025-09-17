<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use App\Traits\HasMetaTagsMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('status')->index()->default(App\Enums\PublicationStatusEnum::PUBLISHED->value);
            $table->string('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('educational_calendar_url')->default(false);
            $table->string('color_scheme')->nullable();
            $this->addMetaTagColumns($table);
            $table->json('properties')->nullable();
            $table->json('additional_info')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff', 'id')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
