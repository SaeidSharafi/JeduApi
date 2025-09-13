<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HomePageBlockTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class HomePageBlock extends Model
{
    use Mediable;
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'location',
        'content',
        'order',
        'is_active',
    ];

    protected $casts = [
        'type' => HomePageBlockTypeEnum::class,
        'content' => 'array',
        'order' => 'integer',
        'is_active' => 'boolean',
    ];
}
