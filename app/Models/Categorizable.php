<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Categorizable extends Model
{
    protected $table = 'categorizables';

    public function categorizable(): MorphTo
    {
        return $this->morphTo();
    }
}
