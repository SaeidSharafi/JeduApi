<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasCategories;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Mediable\Mediable;

final class StudentStory extends Model
{
    use HasFactory, Mediable, HasCategories;

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

    #[Scope]
    public function visible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }
}
