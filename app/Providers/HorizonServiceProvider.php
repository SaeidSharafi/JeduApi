<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Horizon routes are authorized by the stateless middleware configured in config/horizon.php.
     */
    protected function authorization(): void
    {
        Horizon::auth(fn (): bool => true);
    }
}
