<?php

use App\Enums\ReviewStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('reviewable');
            $table->tinyInteger('rating')->nullable();;
            $table->string('title');
            $table->text('comment');
            $table->string('status')->index()->default(ReviewStatusEnum::PENDING->value);
            $table->boolean('is_featured');
            $table->timestamps();

            $table->index(['user_id', 'reviewable_type', 'reviewable_id'], 'user_reviewable_unique_index');
            $table->index(['status', 'is_featured']);
            $table->index(['reviewable_type', 'reviewable_id', 'status', 'is_featured'],
                'reviewable_status_featured_index');
            $table->index(['reviewable_type', 'reviewable_id', 'status'], 'reviewable_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
