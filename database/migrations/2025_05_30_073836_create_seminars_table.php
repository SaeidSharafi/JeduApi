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
        Schema::create('seminars', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('short_name');
            $table->string('subtitle')->nullable();
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('learning_objectives')->nullable();
            $table->text('target_audience')->nullable();
            $table->text('prerequisites')->nullable();

            $table->string('promo_video_external_url')->nullable();

            $table->string('estimated_duration_desc')->nullable();
            $table->enum('level', App\Enums\CourseDifficultyLevelEnum::getAllValues())->nullable();

            $table->boolean('provides_certificate')->default(false);

            $table->json('faq')->nullable()->comment('Store as JSON: [{"q": "...", "a": "..."}, ...]');
            $table->text('keywords')->nullable()->comment('Comma-separated keywords');
            $table->enum('status', App\Enums\PublicationStatusEnum::getAllValues())
                ->default(App\Enums\PublicationStatusEnum::DRAFT->value);
            $table->foreignId('created_by')->nullable()->constrained('admins')->cascadeOnDelete();
            $this->addMetaTagColumns($table);
            $table->timestamps();
        });
    }
};
