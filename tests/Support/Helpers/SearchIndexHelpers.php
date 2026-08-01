<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;

function fakeAfterCommitEventsImmediately(array|string|null $events): object
{
    $transactionManager = app('db.transactions');

    app()->instance('db.transactions', new class
    {
        public function addCallback(callable $callback): void
        {
            $callback();
        }
    });

    if ($events !== null) {
        Event::fake($events);
    }

    return $transactionManager;
}

function restoreAfterCommitEventManager(object $transactionManager): void
{
    app()->instance('db.transactions', $transactionManager);
}
