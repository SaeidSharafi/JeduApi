<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Wallet\WalletCampaignAllocationTriggeredEvent;
use App\Notifications\Wallet\WalletGiftAllocatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Notifies the customer about a campaign gift exactly once per allocation.
 *
 * Queued on the `notifications` queue so it is pushed after the allocation
 * transaction commits (queue connections run with `after_commit`), and a
 * notification failure can never roll back the ledger write. Replays return
 * the prior transaction before the event is dispatched, so no duplicate
 * notification is produced by an idempotent retry.
 */
final class SendWalletGiftAllocatedNotification implements ShouldQueue
{
    public function handle(WalletCampaignAllocationTriggeredEvent $event): void
    {
        $event->user->notify(new WalletGiftAllocatedNotification($event->transaction, $event->campaign));
    }
}
