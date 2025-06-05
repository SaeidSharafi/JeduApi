<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TermStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'academic_year',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => TermStatusEnum::class,
            'start_date' => 'datetime:Y-m-d',
            'end_date' => 'datetime:Y-m-d',
        ];
    }
}
