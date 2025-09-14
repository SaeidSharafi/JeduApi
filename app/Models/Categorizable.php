<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Categorizable extends Model
{
    public $timestamps = false;
    protected $table = 'categorizables';

    protected $fillable = [
        'category_id',
        'categorizable_id',
        'categorizable_type',
        'good_for_start',
    ];

    public function categorizable(): MorphTo
    {
        return $this->morphTo();
    }
}
