<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HomePageBlockTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;

final class HomePageBlock extends Model
{
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
