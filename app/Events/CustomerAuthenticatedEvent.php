<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

class CustomerAuthenticatedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly User $user
    )
    {
    }
}
