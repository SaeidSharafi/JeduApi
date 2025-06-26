<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Plank\Mediable\Mediable;

final class Teacher extends Model
{
    /** @use HasFactory<\Database\Factories\TeacherFactory> */
    use HasFactory;

    use Mediable;

    protected $fillable
        = [
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(ProductDeliveryOption::class);
    }
    protected function casts(): array
    {
        return [
            'social_links' => 'json',
            'rate'         => 'float',
            'gender'       => GenderEnum::class,
            'birth_date'   => 'date:Y-m-d',
            'created_at'   => 'date:Y-m-d H:i:s',
            'updated_at'   => 'date:Y-m-d H:i:s',
        ];
    }
}
