<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_run_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('identity_value')->nullable();
            $table->string('action')->nullable()->index();
            $table->boolean('is_valid');
            $table->jsonb('errors')->nullable();
            $table->jsonb('data');
            $table->timestamps();

            $table->unique(['import_run_id', 'row_number']);
            $table->index(['import_run_id', 'identity_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_run_rows');
    }
};
