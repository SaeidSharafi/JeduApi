<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class CollaborationRequest extends Model
{
    use HasFactory;
    use HasMedia;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'message',
    ];
}
