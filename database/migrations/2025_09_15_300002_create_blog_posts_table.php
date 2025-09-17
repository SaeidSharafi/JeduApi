<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->text('excerpt');
            $table->foreignId('author_id')->nullable()->constrained('staff', 'id')->nullOnDelete();
            $table->string('status')->index()->default(\App\Enums\PublicationStatusEnum::DRAFT->value);
            $table->timestamp('published_at')->nullable();
            $table->integer('read_time_minutes');
            $table->boolean('is_featured')->default(false);
            $table->nullableMorphs('main_productable');
            $table->timestamps();

            $table->index('is_featured');
            $table->index('published_at');
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
