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

    protected function avatarUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(get: function () {
            return $this->firstMedia('avatar')?->getUrl();
        });
    }
}
