<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportExport\UserProvisioningProviderEnum;
use Database\Factories\UserProviderAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Durable account outcome shared across import runs for one user/provider. */
final class UserProviderAccount extends Model
{
    /** @use HasFactory<UserProviderAccountFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'provider', 'idempotency_key', 'status'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['provider' => UserProvisioningProviderEnum::class];
    }
}
