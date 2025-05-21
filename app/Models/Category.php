<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;

class Category extends Model
{
    use HasFactory;
    use Mediable;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'status',
        'description',
        'image_url',
        'icon_url',
        'color_scheme',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'properties',
        'additional_info',
    ];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\PublicationStatusEnum::class,
            'properties' => 'array',
            'additional_info' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
