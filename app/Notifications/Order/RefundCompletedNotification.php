<?php

declare(strict_types=1);

namespace App\Notifications\Order;

use App\Models\Refund;
use App\Notifications\SmsChannel;
use App\Notifications\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RefundCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Refund $refund,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order  = $this->refund->orderItem->order;
        $item   = $this->refund->orderItem;
        $method = $order->payments()->oldest()->value('method');

        $message = (new MailMessage)
            ->subject("استرداد وجه سفارش #{$order->id}")
            ->line("درخواست استرداد وجه برای سفارش شماره {$order->id} تأیید شد.")
            ->line("آیتم: {$item->name}")
            ->line('مبلغ استرداد: '.number_format($this->refund->amount).' ریال');

        $message = match ($method) {
            'digipay' => $message->line('کد رهگیری: '.($this->refund->transaction_details['gateway_tracking_code'] ?? '—')),
            'wallet'  => $message->line('مبلغ به کیف پول شما اضافه شد.'),
            'bank_transfer',
            'mellat_gateway' => $message->line('مبلغ توسط مدیر سیستم به حساب شما واریز خواهد شد.'),
            default          => $message,
        };

        return $message->line('دسترسی شما به این دوره لغو شده است.');
    }

    public function toSms(object $notifiable): SmsMessage
    {
        $orderId = $this->refund->orderItem->order_id;
        $amount  = number_format($this->refund->amount);

        return (new SmsMessage)
            ->content("استرداد وجه سفارش #{$orderId} به مبلغ {$amount} ریال تأیید شد.")
            ->type('REFUND');
    }
}
