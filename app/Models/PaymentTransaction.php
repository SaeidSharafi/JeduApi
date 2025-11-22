<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Payment\PaymentTransactionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'transaction_reference',
        'attempt_number',
        'status',
        'gateway_request',
        'gateway_response',
        'initiated_at',
        'completed_at',
        'error_code',
        'error_message',
        'ip_address',
        'user_agent',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'status'           => PaymentTransactionStatusEnum::class,
            'gateway_request'  => 'array',
            'gateway_response' => 'array',
            'initiated_at'     => 'datetime',
            'completed_at'     => 'datetime',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }
}
