<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\Order\RefundCompletedNotification;
use App\Notifications\SmsChannel;
use App\Notifications\SmsMessage;

/**
 * Build a complete refund graph: Order → OrderItem → Refund,
 * optionally with a Payment row on the order.
 *
 * @param  array<string, mixed>  $transactionDetails
 */
function createRefundGraph(?string $paymentMethod = null, array $transactionDetails = []): Refund
{
    $order = Order::factory()->create();
    $item  = OrderItem::factory()->create([
        'order_id' => $order->id,
        'name'     => 'دوره آموزشی تستی',
        'price'    => 250_000,
    ]);

    $refund = Refund::factory()->create([
        'order_id'            => $order->id,
        'order_item_id'       => $item->id,
        'amount'              => 250_000,
        'transaction_details' => $transactionDetails,
        'refunded_at'         => now(),
    ]);

    if ($paymentMethod !== null) {
        Payment::factory()->create([
            'order_id'   => $order->id,
            'method'     => $paymentMethod,
            'amount'     => 250_000,
            'created_at' => now()->subDay(),
        ]);
    }

    return $refund;
}

describe('RefundCompletedNotification', function (): void {
    it('sends through the mail and sms channels', function (): void {
        $refund       = createRefundGraph('digipay');
        $notification = new RefundCompletedNotification($refund);

        expect($notification->via(new User()))->toBe(['mail', SmsChannel::class]);
    });

    it('renders the common refund lines for every mail', function (): void {
        $refund = createRefundGraph('digipay');
        $mail   = (new RefundCompletedNotification($refund))->toMail(new User());

        expect($mail->subject)->toBe("استرداد وجه سفارش #{$refund->orderItem->order->id}")
            ->and($mail->introLines)->toContain(
                "درخواست استرداد وجه برای سفارش شماره {$refund->orderItem->order->id} تأیید شد."
            )
            ->and($mail->introLines)->toContain('آیتم: دوره آموزشی تستی')
            ->and($mail->introLines)->toContain('مبلغ استرداد: '.number_format(250_000).' ریال')
            ->and($mail->introLines)->toContain('دسترسی شما به این دوره لغو شده است.');
    });

    it('adds the payment-method specific line', function (string $method, array $transactionDetails, string $expectedLine): void {
        $refund = createRefundGraph($method, $transactionDetails);
        $mail   = (new RefundCompletedNotification($refund))->toMail(new User());

        expect($mail->introLines)->toContain($expectedLine)
            ->and($mail->subject)->toBe("استرداد وجه سفارش #{$refund->orderItem->order->id}");
    })->with([
        'digipay with tracking code' => ['digipay', ['gateway_tracking_code' => 'TRACK123'], 'کد رهگیری: TRACK123'],
        'wallet'                     => ['wallet', [], 'مبلغ به کیف پول شما اضافه شد.'],
        'bank transfer'              => ['bank_transfer', [], 'مبلغ توسط مدیر سیستم به حساب شما واریز خواهد شد.'],
        'mellat gateway'             => ['mellat_gateway', [], 'مبلغ توسط مدیر سیستم به حساب شما واریز خواهد شد.'],
    ]);

    it('does not show a tracking code line for wallet refunds', function (): void {
        // Tracking details exist, but the wallet branch must ignore them.
        $refund = createRefundGraph('wallet', ['gateway_tracking_code' => 'TRACK123']);
        $mail   = (new RefundCompletedNotification($refund))->toMail(new User());

        expect($mail->introLines)->toContain('مبلغ به کیف پول شما اضافه شد.')
            ->not->toContain('کد رهگیری: TRACK123');
    });

    it('uses the oldest payment method when multiple payments exist', function (): void {
        $refund = createRefundGraph('wallet', ['gateway_tracking_code' => 'TRACK123']);
        $order  = $refund->orderItem->order;

        // A digipay payment is older than the wallet payment → digipay branch wins.
        Payment::factory()->create([
            'order_id'   => $order->id,
            'method'     => 'digipay',
            'amount'     => 100_000,
            'created_at' => now()->subDays(2),
        ]);

        $mail = (new RefundCompletedNotification($refund))->toMail(new User());

        expect($mail->introLines)->toContain('کد رهگیری: TRACK123');
    });

    it('renders no method-specific line when the order has no payment', function (): void {
        $refund = createRefundGraph();
        $mail   = (new RefundCompletedNotification($refund))->toMail(new User());

        expect($mail->introLines)->toHaveCount(4)
            ->and($mail->introLines)->not->toContain('کد رهگیری: ')
            ->and($mail->introLines)->not->toContain('مبلغ به کیف پول شما اضافه شد.')
            ->and($mail->introLines)->not->toContain('مبلغ توسط مدیر سیستم به حساب شما واریز خواهد شد.')
            ->and(end($mail->introLines))->toBe('دسترسی شما به این دوره لغو شده است.');
    });

    it('builds the sms message with refund type and order details', function (): void {
        $refund = createRefundGraph('digipay');
        $sms    = (new RefundCompletedNotification($refund))->toSms(new User());

        expect($sms)->toBeInstanceOf(SmsMessage::class)
            ->and($sms->type)->toBe('REFUND')
            ->and($sms->content)->toContain("استرداد وجه سفارش #{$refund->orderItem->order_id}")
            ->and($sms->content)->toContain(number_format(250_000).' ریال');
    });
});
