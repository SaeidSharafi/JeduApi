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
        Schema::create('digital_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version', 50)->nullable();
            $table->unsignedInteger('page_count')->nullable()->comment('For documents like PDFs');
            $table->unsignedInteger('duration_seconds')->nullable()->comment('For audio/video file types');
            $table->boolean('is_attachable_to_course')->default(false);
            $table->enum('status', App\Enums\PublicationStatusEnum::getAllValues())
                ->default(App\Enums\PublicationStatusEnum::DRAFT->value);
            $table->text('keywords')->nullable()->comment('Comma-separated keywords');
            $this->addMetaTagColumns($table);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff', 'id')->nullOnDelete();
            $table->timestamps();
        });
    }
};
