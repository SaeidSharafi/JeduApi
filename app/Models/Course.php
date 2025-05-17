<?php

namespace App\Models;

use App\Enums\CourseStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'short_name',
        'description',
        'default_teahcer_info',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'created_by'
    ];

    protected $casts = [
        'status' => CourseStatusEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
