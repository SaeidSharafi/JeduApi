<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\ContactUsRequestFactory;

final class ContactUsRequest extends Model
{
    /** @use HasFactory<ContactUsRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone',
        'subject',
        'email',
        'message',
    ];
}
