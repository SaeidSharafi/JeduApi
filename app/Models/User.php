<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CivilIdTypeEnum;
use App\Enums\EducationLevelEnum;
use App\Enums\EducationStatusEnum;
use App\Enums\GenderEnum;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
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
        return ! is_null($this->password);
    }

    public function teacherData(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'customer_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function profileCompleted(): bool
    {
        return $this->first_name    !== null
            && $this->last_name     !== null
            && $this->email         !== null
            && $this->phone         !== null
            && $this->civil_id      !== null
            && $this->date_of_birth !== null
            && $this->father_name   !== null;
    }

    public function routeNotificationForSms($notification): string
    {
        return $this->phone;
    }

    protected static function boot(): void
    {
        parent::boot();
        self::creating(function ($model): void {
            $model->uuid = (string) Str::uuid7();
        });

        self::created(function ($model): void {
            // Automatically create a wallet for new users
            $model->wallet()->create([
                'balance'      => 0,
                'gift_balance' => 0,
                'status'       => \App\Enums\Wallet\WalletStatusEnum::ACTIVE,
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'date_of_birth'     => 'date:Y-m-d',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'education_level'   => EducationLevelEnum::class,
            'education_status'  => EducationStatusEnum::class,
            'gender'            => GenderEnum::class,
            'civil_id_type'     => CivilIdTypeEnum::class,
        ];
    }

    protected function isProfileCompleted(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->profileCompleted(),
        );
    }
}
