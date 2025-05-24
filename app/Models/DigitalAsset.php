<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;

final class DigitalAsset extends Model
{
    use HasFactory;
    use Mediable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'page_count',
        'duration_seconds',
        'is_attachable_to_course',
        'status',
        'keywords',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'status'       => \App\Enums\PublicationStatusEnum::class,
        'published_at' => 'datetime:Y-m-d H:i:s',
        'created_at'   => 'datetime:Y-m-d H:i:s',
        'updated_at'   => 'datetime:Y-m-d H:i:s',
    ];
}
