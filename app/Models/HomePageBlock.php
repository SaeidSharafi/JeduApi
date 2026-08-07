<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Content\HomePageBlockTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;
use Database\Factories\HomePageBlockFactory;

final class HomePageBlock extends Model
{
    /** @use HasFactory<HomePageBlockFactory> */
    use HasFactory;
    use Mediable;

    protected $fillable = [
        'type',
        'title',
        'location',
        'content',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type'      => HomePageBlockTypeEnum::class,
            'content'   => 'array',
            'order'     => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
