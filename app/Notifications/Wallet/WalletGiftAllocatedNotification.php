<?php

declare(strict_types=1);

namespace App\Notifications\Wallet;

use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells a customer that a wallet campaign credited a gift to their wallet.
 *
 * Database only: the notification is read back through the customer
 * notification feed. SMS stays a separate, admin-configurable option
 * (`SmsNotificationOptionEnum::WALLET_CAMPAIGN_CREDITED`).
 */
final class WalletGiftAllocatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Public notification kind carried in the payload.
     *
     * The feed returns this instead of the notification class name: the class is
     * an internal detail, and a public API must not expose the code's namespace.
     */
    public const string DATABASE_TYPE = 'wallet_gift_allocated';

    public function __construct(
        public WalletTransaction $transaction,
        public WalletCampaign $campaign,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, title: string, message: string, resource_type: string, resource_id: int}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => self::DATABASE_TYPE,
            'title'   => (string) __('messages.notifications.wallet_gift_allocated.title'),
            'message' => (string) __('messages.notifications.wallet_gift_allocated.message', [
                'amount'   => number_format((int) $this->transaction->amount),
                'campaign' => $this->campaign->name,
            ]),
            'resource_type' => 'wallet_transaction',
            'resource_id'   => (int) $this->transaction->id,
        ];
    }
}
