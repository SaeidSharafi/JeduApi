<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EducationLevelEnum;
use App\Enums\EducationStatusEnum;
use App\Enums\GenderEnum;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    protected string $guard_name = 'user';

    protected $fillable
        = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'password',
            'phone2',
            'civil_id',
            'civil_id_type',
            'date_of_birth',
            'father_name',
            'gender',
            'education_level',
            'field_of_study',
            'education_status',
        ];

    protected $hidden
        = [
            'password',
            'remember_token',
        ];

    public function hasSetPassword(): bool
    {
        return !is_null($this->password);
    }

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'date_of_birth'     => 'date:Y-m-d',
            'created_at'        => 'datetime:Y-m-d H:i:s',
            'updated_at'        => 'datetime:Y-m-d H:i:s',
            'education_level'   => EducationLevelEnum::class,
            'education_status'  => EducationStatusEnum::class,
            'gender'            => GenderEnum::class,
        ];
    }

    public function teacherData(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function profileCompleted(): bool
    {
        return $this->first_name !== null
            && $this->last_name !== null
            && $this->email !== null
            && $this->phone !== null
            && $this->national_code !== null
            && $this->date_of_birth !== null
            && $this->father_name !== null;
    }
}
