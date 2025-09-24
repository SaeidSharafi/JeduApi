<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;

final class StudentStory extends Model
{
    use HasFactory, Mediable;

    protected $fillable = [
        'student_name',
        'course_name',
        'course_url',
        'story_text',
        'is_visible',
        'display_order',
    ];

    /**
     * Scope a query to only include visible student stories.
     *
     * Applies a `where` constraint for `is_visible = true`.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder to modify.
     * @return \Illuminate\Database\Eloquent\Builder The modified query builder.
     */
    #[Scope]
    public function visible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }
    /**
     * Return the model's attribute cast definitions.
     *
     * Provides an array mapping attribute names to their cast types. Here, both
     * `created_at` and `updated_at` are cast to `datetime`.
     *
     * @return array<string,string> Attribute => cast type mappings.
     */
    protected function casts(): array
    {
        return [
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    protected function avatarUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(get: function () {
            return $this->firstMedia('avatar')?->getUrl();
        });
    }
}
