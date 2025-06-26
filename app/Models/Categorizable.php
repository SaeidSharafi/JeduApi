<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Categorizable extends Model
{
    protected $table = 'categorizables';

    public function categorizable(): MorphTo
    {
        return $this->morphTo();
    }
}
