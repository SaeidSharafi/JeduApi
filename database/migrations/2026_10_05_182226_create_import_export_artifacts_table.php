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
        Schema::create('import_export_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->string('resource')->index();
            $table->uuid('artifact_uuid')->unique();
            $table->string('path')->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('cleanup_retry_after')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_export_artifacts');
    }
};
