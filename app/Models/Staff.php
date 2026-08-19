<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\WalletTransactionSourceableContract;
use Database\Factories\StaffFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class Staff extends Authenticatable implements MustVerifyEmail, WalletTransactionSourceableContract
{
    use HasApiTokens;

    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    protected string $guard_name = 'staff';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_banned',
        'banned_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function hasSetPassword(): bool
    {
        return ! is_null($this->password);
    }

    public function routeNotificationForSms(mixed $notification): string
    {
        return $this->phone;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_banned'         => 'boolean',
            'banned_at'         => 'datetime',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }
}
