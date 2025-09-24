<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ContactUsRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone',
        'subject',
        'email',
        'message',
    ];
}
