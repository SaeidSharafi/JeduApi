<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Plank\Mediable\Mediable;

class Teacher extends Model
{
    /** @use HasFactory<\Database\Factories\TeacherFactory> */
    use HasFactory;
    use Mediable;

    protected $fillable = [
        'first_name',
        'last_name',
        'bio',
        'rate',
        'email',
        'phone',
        'gender',
        'birth_date',
        'social_links',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'json',
            'rate' => 'float',
            'birth_date' => 'date:Y-m-d',
            'created_at' => 'date:Y-m-d H:i:s',
            'updated_at' => 'date:Y-m-d H:i:s',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
