<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProductCacheInvalidated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $productId) {}
}
