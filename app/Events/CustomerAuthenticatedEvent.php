<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

final class CustomerAuthenticatedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly User $user
    ) {}
}
