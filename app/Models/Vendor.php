<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Plank\Mediable\Mediable;

class Vendor extends Model
{
    /** @use HasFactory<\Database\Factories\VendorFactory> */
    use HasFactory;
    use Mediable;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone2',
        'address',
        'map_location',
        'logo_url',
        'favicon_url',
        'social_links',
        'theme_options',

    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'json',
            'theme_options' => 'json',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
