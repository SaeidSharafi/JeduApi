<?php

namespace App\Events;

use App\Contracts\OtpTypeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OtpPrepared
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, string>  $params
     */
    public function __construct(
        public readonly string $identifier,
        public readonly string $guard,
        public readonly string $code,
        public readonly ?OtpTypeInterface $type,
        public string $trackingCode,
        public readonly array $params,
    ) {}
}
