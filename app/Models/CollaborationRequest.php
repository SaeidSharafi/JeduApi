<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;

final class CollaborationRequest extends Model
{
    use HasFactory;
    use Mediable;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'department',
        'message',
    ];
}
