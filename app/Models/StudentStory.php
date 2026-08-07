<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasCategories;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Plank\Mediable\Mediable;
use Database\Factories\StudentStoryFactory;

final class StudentStory extends Model
{
    /** @use HasFactory<StudentStoryFactory> */
    use HasCategories, HasFactory, Mediable;

    protected $fillable
        = [
            'student_name',
            'course_name',
            'avatar_url',
            'course_url',
            'story_text',
            'is_visible',
            'is_featured',
            'display_order',
        ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function featured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
